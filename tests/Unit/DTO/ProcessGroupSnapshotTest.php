<?php

declare(strict_types=1);

use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessResourceUsage;
use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot;

it('can be instantiated with root and children', function () {
    $rootCpu = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $rootResources = new ProcessResourceUsage(
        cpuTimes: $rootCpu,
        memoryRssBytes: 10485760,
        memoryVmsBytes: 20971520,
        threadCount: 4,
        openFileDescriptors: 10
    );

    $timestamp = new DateTimeImmutable;

    $root = new ProcessSnapshot(
        pid: 1234,
        parentPid: 1,
        resources: $rootResources,
        timestamp: $timestamp
    );

    $childCpu = new CpuTimes(
        user: 50,
        nice: 0,
        system: 25,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $childResources = new ProcessResourceUsage(
        cpuTimes: $childCpu,
        memoryRssBytes: 5242880,
        memoryVmsBytes: 10485760,
        threadCount: 2,
        openFileDescriptors: 5
    );

    $child = new ProcessSnapshot(
        pid: 1235,
        parentPid: 1234,
        resources: $childResources,
        timestamp: $timestamp
    );

    $group = new ProcessGroupSnapshot(
        rootPid: 1234,
        root: $root,
        children: [$child],
        timestamp: $timestamp
    );

    expect($group->rootPid)->toBe(1234);
    expect($group->root)->toBe($root);
    expect($group->children)->toBe([$child]);
    expect($group->timestamp)->toBe($timestamp);
});

it('calculates total process count correctly', function () {
    $cpu = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $resources = new ProcessResourceUsage(
        cpuTimes: $cpu,
        memoryRssBytes: 10485760,
        memoryVmsBytes: 20971520,
        threadCount: 4,
        openFileDescriptors: 10
    );

    $timestamp = new DateTimeImmutable;

    $root = new ProcessSnapshot(
        pid: 1234,
        parentPid: 1,
        resources: $resources,
        timestamp: $timestamp
    );

    $child1 = new ProcessSnapshot(
        pid: 1235,
        parentPid: 1234,
        resources: $resources,
        timestamp: $timestamp
    );

    $child2 = new ProcessSnapshot(
        pid: 1236,
        parentPid: 1234,
        resources: $resources,
        timestamp: $timestamp
    );

    $child3 = new ProcessSnapshot(
        pid: 1237,
        parentPid: 1234,
        resources: $resources,
        timestamp: $timestamp
    );

    $group = new ProcessGroupSnapshot(
        rootPid: 1234,
        root: $root,
        children: [$child1, $child2, $child3],
        timestamp: $timestamp
    );

    expect($group->totalProcessCount())->toBe(4); // 1 root + 3 children
});

it('handles process group with no children', function () {
    $cpu = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $resources = new ProcessResourceUsage(
        cpuTimes: $cpu,
        memoryRssBytes: 10485760,
        memoryVmsBytes: 20971520,
        threadCount: 4,
        openFileDescriptors: 10
    );

    $timestamp = new DateTimeImmutable;

    $root = new ProcessSnapshot(
        pid: 1234,
        parentPid: 1,
        resources: $resources,
        timestamp: $timestamp
    );

    $group = new ProcessGroupSnapshot(
        rootPid: 1234,
        root: $root,
        children: [],
        timestamp: $timestamp
    );

    expect($group->totalProcessCount())->toBe(1);
    expect($group->children)->toBeEmpty();
});

it('aggregates memory RSS correctly', function () {
    $rootCpu = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $rootResources = new ProcessResourceUsage(
        cpuTimes: $rootCpu,
        memoryRssBytes: 10485760, // 10 MB
        memoryVmsBytes: 20971520,
        threadCount: 4,
        openFileDescriptors: 10
    );

    $timestamp = new DateTimeImmutable;

    $root = new ProcessSnapshot(
        pid: 1234,
        parentPid: 1,
        resources: $rootResources,
        timestamp: $timestamp
    );

    $child1Resources = new ProcessResourceUsage(
        cpuTimes: $rootCpu,
        memoryRssBytes: 5242880, // 5 MB
        memoryVmsBytes: 10485760,
        threadCount: 2,
        openFileDescriptors: 5
    );

    $child2Resources = new ProcessResourceUsage(
        cpuTimes: $rootCpu,
        memoryRssBytes: 3145728, // 3 MB
        memoryVmsBytes: 6291456,
        threadCount: 1,
        openFileDescriptors: 3
    );

    $child1 = new ProcessSnapshot(
        pid: 1235,
        parentPid: 1234,
        resources: $child1Resources,
        timestamp: $timestamp
    );

    $child2 = new ProcessSnapshot(
        pid: 1236,
        parentPid: 1234,
        resources: $child2Resources,
        timestamp: $timestamp
    );

    $group = new ProcessGroupSnapshot(
        rootPid: 1234,
        root: $root,
        children: [$child1, $child2],
        timestamp: $timestamp
    );

    // 10 + 5 + 3 = 18 MB
    expect($group->aggregateMemoryRss())->toBe(18874368);
});

it('aggregates memory VMS correctly', function () {
    $rootCpu = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $rootResources = new ProcessResourceUsage(
        cpuTimes: $rootCpu,
        memoryRssBytes: 10485760,
        memoryVmsBytes: 20971520, // 20 MB
        threadCount: 4,
        openFileDescriptors: 10
    );

    $timestamp = new DateTimeImmutable;

    $root = new ProcessSnapshot(
        pid: 1234,
        parentPid: 1,
        resources: $rootResources,
        timestamp: $timestamp
    );

    $child1Resources = new ProcessResourceUsage(
        cpuTimes: $rootCpu,
        memoryRssBytes: 5242880,
        memoryVmsBytes: 10485760, // 10 MB
        threadCount: 2,
        openFileDescriptors: 5
    );

    $child2Resources = new ProcessResourceUsage(
        cpuTimes: $rootCpu,
        memoryRssBytes: 3145728,
        memoryVmsBytes: 6291456, // 6 MB
        threadCount: 1,
        openFileDescriptors: 3
    );

    $child1 = new ProcessSnapshot(
        pid: 1235,
        parentPid: 1234,
        resources: $child1Resources,
        timestamp: $timestamp
    );

    $child2 = new ProcessSnapshot(
        pid: 1236,
        parentPid: 1234,
        resources: $child2Resources,
        timestamp: $timestamp
    );

    $group = new ProcessGroupSnapshot(
        rootPid: 1234,
        root: $root,
        children: [$child1, $child2],
        timestamp: $timestamp
    );

    // 20 + 10 + 6 = 36 MB
    expect($group->aggregateMemoryVms())->toBe(37748736);
});

it('handles empty children array in aggregation', function () {
    $cpu = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $resources = new ProcessResourceUsage(
        cpuTimes: $cpu,
        memoryRssBytes: 10485760,
        memoryVmsBytes: 20971520,
        threadCount: 4,
        openFileDescriptors: 10
    );

    $timestamp = new DateTimeImmutable;

    $root = new ProcessSnapshot(
        pid: 1234,
        parentPid: 1,
        resources: $resources,
        timestamp: $timestamp
    );

    $group = new ProcessGroupSnapshot(
        rootPid: 1234,
        root: $root,
        children: [],
        timestamp: $timestamp
    );

    expect($group->aggregateMemoryRss())->toBe(10485760);
    expect($group->aggregateMemoryVms())->toBe(20971520);
});

it('handles large process group', function () {
    $cpu = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $resources = new ProcessResourceUsage(
        cpuTimes: $cpu,
        memoryRssBytes: 1048576, // 1 MB per process
        memoryVmsBytes: 2097152, // 2 MB per process
        threadCount: 1,
        openFileDescriptors: 5
    );

    $timestamp = new DateTimeImmutable;

    $root = new ProcessSnapshot(
        pid: 1234,
        parentPid: 1,
        resources: $resources,
        timestamp: $timestamp
    );

    // Create 99 children
    $children = [];
    for ($i = 0; $i < 99; $i++) {
        $children[] = new ProcessSnapshot(
            pid: 1235 + $i,
            parentPid: 1234,
            resources: $resources,
            timestamp: $timestamp
        );
    }

    $group = new ProcessGroupSnapshot(
        rootPid: 1234,
        root: $root,
        children: $children,
        timestamp: $timestamp
    );

    expect($group->totalProcessCount())->toBe(100); // 1 root + 99 children
    expect($group->aggregateMemoryRss())->toBe(104857600); // 100 MB total
    expect($group->aggregateMemoryVms())->toBe(209715200); // 200 MB total
});

it('is immutable', function () {
    $cpu = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $resources = new ProcessResourceUsage(
        cpuTimes: $cpu,
        memoryRssBytes: 10485760,
        memoryVmsBytes: 20971520,
        threadCount: 4,
        openFileDescriptors: 10
    );

    $timestamp = new DateTimeImmutable;

    $root = new ProcessSnapshot(
        pid: 1234,
        parentPid: 1,
        resources: $resources,
        timestamp: $timestamp
    );

    $group = new ProcessGroupSnapshot(
        rootPid: 1234,
        root: $root,
        children: [],
        timestamp: $timestamp
    );

    $reflection = new ReflectionClass($group);
    expect($reflection->isReadOnly())->toBeTrue();
});
