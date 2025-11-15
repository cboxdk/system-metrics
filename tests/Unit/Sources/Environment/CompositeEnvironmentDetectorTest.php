<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Contracts\EnvironmentDetector;
use PHPeek\SystemMetrics\DTO\Environment\Architecture;
use PHPeek\SystemMetrics\DTO\Environment\ArchitectureKind;
use PHPeek\SystemMetrics\DTO\Environment\Cgroup;
use PHPeek\SystemMetrics\DTO\Environment\CgroupVersion;
use PHPeek\SystemMetrics\DTO\Environment\Containerization;
use PHPeek\SystemMetrics\DTO\Environment\ContainerType;
use PHPeek\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use PHPeek\SystemMetrics\DTO\Environment\Kernel;
use PHPeek\SystemMetrics\DTO\Environment\OperatingSystem;
use PHPeek\SystemMetrics\DTO\Environment\OsFamily;
use PHPeek\SystemMetrics\DTO\Environment\Virtualization;
use PHPeek\SystemMetrics\DTO\Environment\VirtualizationType;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;
use PHPeek\SystemMetrics\Sources\Environment\CompositeEnvironmentDetector;

describe('CompositeEnvironmentDetector', function () {
    it('creates OS-specific detector when none provided', function () {
        $detector = new CompositeEnvironmentDetector;

        $result = $detector->detect();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('uses injected detector when provided', function () {
        $mockDetector = new class implements EnvironmentDetector
        {
            public function detect(): Result
            {
                return Result::success(
                    new EnvironmentSnapshot(
                        os: new OperatingSystem(
                            family: OsFamily::Linux,
                            name: 'Ubuntu',
                            version: '22.04'
                        ),
                        kernel: new Kernel(
                            release: '5.15.0',
                            version: '#1 SMP'
                        ),
                        architecture: new Architecture(
                            kind: ArchitectureKind::X86_64,
                            raw: 'x86_64'
                        ),
                        virtualization: new Virtualization(
                            type: VirtualizationType::BareMetal,
                            vendor: null,
                            rawIdentifier: null
                        ),
                        containerization: new Containerization(
                            type: ContainerType::None,
                            runtime: null,
                            insideContainer: false,
                            rawIdentifier: null
                        ),
                        cgroup: new Cgroup(
                            version: CgroupVersion::V2,
                            cpuPath: '/',
                            memoryPath: '/'
                        )
                    )
                );
            }
        };

        $composite = new CompositeEnvironmentDetector($mockDetector);
        $result = $composite->detect();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(EnvironmentSnapshot::class);
        expect($snapshot->os->name)->toBe('Ubuntu');
    });

    it('delegates detection to underlying detector', function () {
        $mockDetector = new class implements EnvironmentDetector
        {
            public function detect(): Result
            {
                return Result::success(
                    new EnvironmentSnapshot(
                        os: new OperatingSystem(
                            family: OsFamily::MacOs,
                            name: 'macOS',
                            version: '14.0'
                        ),
                        kernel: new Kernel(
                            release: '23.0.0',
                            version: 'Darwin'
                        ),
                        architecture: new Architecture(
                            kind: ArchitectureKind::Arm64,
                            raw: 'arm64'
                        ),
                        virtualization: new Virtualization(
                            type: VirtualizationType::BareMetal,
                            vendor: null,
                            rawIdentifier: null
                        ),
                        containerization: new Containerization(
                            type: ContainerType::None,
                            runtime: null,
                            insideContainer: false,
                            rawIdentifier: null
                        ),
                        cgroup: new Cgroup(
                            version: CgroupVersion::None,
                            cpuPath: null,
                            memoryPath: null
                        )
                    )
                );
            }
        };

        $composite = new CompositeEnvironmentDetector($mockDetector);
        $result = $composite->detect();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(EnvironmentSnapshot::class);
        expect($result->getValue()->architecture->kind)->toBe(ArchitectureKind::Arm64);
    });

    it('propagates errors from underlying detector', function () {
        $mockDetector = new class implements EnvironmentDetector
        {
            public function detect(): Result
            {
                return Result::failure(
                    UnsupportedOperatingSystemException::forOs('Windows')
                );
            }
        };

        $composite = new CompositeEnvironmentDetector($mockDetector);
        $result = $composite->detect();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(UnsupportedOperatingSystemException::class);
    });
});
