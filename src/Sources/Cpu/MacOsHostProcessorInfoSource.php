<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Cpu;

use FFI;
use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Reads CPU metrics from macOS using host_processor_info() via FFI.
 *
 * This is the preferred method for modern macOS systems as it provides:
 * - ⚡ Fast performance (~0.01ms vs ~1100ms for `top` command)
 * - 📊 Accurate cumulative CPU ticks (same as Linux /proc/stat)
 * - 🎯 Per-core data with precise metrics
 * - 🔒 Stable API (native Mach kernel calls)
 *
 * Requires PHP FFI extension (enabled by default in PHP 7.4+).
 */
final class MacOsHostProcessorInfoSource implements CpuMetricsSource
{
    /** @var FFI|null Cached FFI instance */
    private static ?FFI $ffi = null;

    /** @var int PROCESSOR_CPU_LOAD_INFO flavor constant */
    private const PROCESSOR_CPU_LOAD_INFO = 2;

    /** @var int CPU_STATE_USER index */
    private const CPU_STATE_USER = 0;

    /** @var int CPU_STATE_SYSTEM index */
    private const CPU_STATE_SYSTEM = 1;

    /** @var int CPU_STATE_IDLE index */
    private const CPU_STATE_IDLE = 2;

    /** @var int CPU_STATE_NICE index */
    private const CPU_STATE_NICE = 3;

    public function read(): Result
    {
        if (! extension_loaded('ffi')) {
            /** @var Result<CpuSnapshot> */
            return Result::failure(
                new SystemMetricsException('FFI extension not available')
            );
        }

        try {
            $ffi = $this->getFFI();

            // @phpstan-ignore method.notFound (FFI methods defined via cdef)
            $host = $ffi->mach_host_self();
            $processor_count = $ffi->new('natural_t');
            $processor_info_addr = $ffi->new('vm_address_t');
            $processor_info_count = $ffi->new('natural_t');

            // @phpstan-ignore method.notFound (FFI methods defined via cdef)
            $kr = $ffi->host_processor_info(
                $host,
                self::PROCESSOR_CPU_LOAD_INFO,
                \FFI::addr($processor_count),
                \FFI::addr($processor_info_addr),
                \FFI::addr($processor_info_count)
            );

            if ($kr !== 0) {
                /** @var Result<CpuSnapshot> */
                return Result::failure(
                    new SystemMetricsException("host_processor_info failed with kern_return_t: {$kr}")
                );
            }

            // @phpstan-ignore property.notFound (FFI properties defined via cdef)
            $num_cpus = $processor_count->cdata;
            // @phpstan-ignore property.notFound
            $info_count = $processor_info_count->cdata;
            // @phpstan-ignore property.notFound
            $data_addr = $processor_info_addr->cdata;

            // Read CPU data from kernel memory using FFI::memcpy
            $data = $ffi->new("int[{$info_count}]");
            $void_ptr = $ffi->cast('void*', $data_addr);
            \FFI::memcpy($data, $void_ptr, $info_count * 4);

            // Parse CPU times
            $snapshot = $this->parseSnapshot($data, $num_cpus);

            // Cleanup kernel memory
            $ffi->vm_deallocate( // @phpstan-ignore method.notFound
                $ffi->mach_task_self_, // @phpstan-ignore property.notFound
                $data_addr,
                $info_count * 4
            );

            return Result::success($snapshot);

        } catch (\Throwable $e) {
            /** @var Result<CpuSnapshot> */
            return Result::failure(
                new SystemMetricsException(
                    'Failed to read CPU metrics via FFI: '.$e->getMessage(),
                    previous: $e
                )
            );
        }
    }

    /**
     * Parse CPU snapshot from raw data.
     *
     * @param  \FFI\CData  $data  Raw CPU data array (4 values per core)
     * @param  int  $num_cpus  Number of CPU cores
     */
    private function parseSnapshot(\FFI\CData $data, int $num_cpus): CpuSnapshot
    {
        // Calculate system-wide totals
        $total_user = 0;
        $total_system = 0;
        $total_idle = 0;
        $total_nice = 0;

        for ($i = 0; $i < $num_cpus; $i++) {
            $base = $i * 4;
            $total_user += $data[$base + self::CPU_STATE_USER];
            $total_system += $data[$base + self::CPU_STATE_SYSTEM];
            $total_idle += $data[$base + self::CPU_STATE_IDLE];
            $total_nice += $data[$base + self::CPU_STATE_NICE];
        }

        $totalTimes = new CpuTimes(
            user: $total_user,
            nice: $total_nice,
            system: $total_system,
            idle: $total_idle,
            iowait: 0, // macOS doesn't track iowait separately
            irq: 0,
            softirq: 0,
            steal: 0
        );

        // Parse per-core data
        $perCore = [];
        for ($i = 0; $i < $num_cpus; $i++) {
            $base = $i * 4;

            $coreTimes = new CpuTimes(
                user: $data[$base + self::CPU_STATE_USER],
                nice: $data[$base + self::CPU_STATE_NICE],
                system: $data[$base + self::CPU_STATE_SYSTEM],
                idle: $data[$base + self::CPU_STATE_IDLE],
                iowait: 0,
                irq: 0,
                softirq: 0,
                steal: 0
            );

            $perCore[] = new CpuCoreTimes(
                coreIndex: $i,
                times: $coreTimes
            );
        }

        return new CpuSnapshot(
            total: $totalTimes,
            perCore: $perCore
        );
    }

    /**
     * Get or create FFI instance (cached for performance).
     */
    private function getFFI(): FFI
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef('
                typedef unsigned int mach_port_t;
                typedef int kern_return_t;
                typedef unsigned int natural_t;
                typedef int processor_flavor_t;
                typedef unsigned long vm_address_t;
                typedef unsigned long vm_size_t;

                extern mach_port_t mach_host_self(void);
                extern mach_port_t mach_task_self_;

                kern_return_t host_processor_info(
                    mach_port_t host,
                    processor_flavor_t flavor,
                    natural_t *out_processor_count,
                    vm_address_t *out_processor_info,
                    natural_t *out_processor_info_count
                );

                kern_return_t vm_deallocate(
                    mach_port_t target_task,
                    vm_address_t address,
                    vm_size_t size
                );
            ', 'libSystem.dylib');
        }

        return self::$ffi;
    }
}
