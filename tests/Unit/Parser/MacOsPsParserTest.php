<?php

declare(strict_types=1);

use Cbox\SystemMetrics\Contracts\ProcessRunnerInterface;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\ParseException;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use Cbox\SystemMetrics\Support\Parser\MacOsPsParser;

it('can parse ps command output', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 1234     1  10240    20480  00:01:30
PS;

    $result = $parser->parse($output, 1234);

    expect($result->isSuccess())->toBeTrue();

    $snapshot = $result->getValue();
    expect($snapshot->pid)->toBe(1234);
    expect($snapshot->parentPid)->toBe(1);
    expect($snapshot->resources->memoryRssBytes)->toBe(10485760); // 10240 KB * 1024
    expect($snapshot->resources->memoryVmsBytes)->toBe(20971520); // 20480 KB * 1024
    expect($snapshot->resources->cpuTimes->user)->toBe(9000); // 90 seconds * 100 ticks/sec
    expect($snapshot->resources->threadCount)->toBe(1);
});

it('parses HH:MM:SS time format correctly', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 5678   100  5120    10240  01:30:45
PS;

    $result = $parser->parse($output, 5678);

    expect($result->isSuccess())->toBeTrue();
    // 1 hour + 30 minutes + 45 seconds = 5445 seconds * 100 ticks/sec = 544500 ticks
    expect($result->getValue()->resources->cpuTimes->user)->toBe(544500);
});

it('parses MM:SS time format correctly', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 5678   100  5120    10240  05:30
PS;

    $result = $parser->parse($output, 5678);

    expect($result->isSuccess())->toBeTrue();
    // 5 minutes + 30 seconds = 330 seconds * 100 ticks/sec = 33000 ticks
    expect($result->getValue()->resources->cpuTimes->user)->toBe(33000);
});

it('handles time with centiseconds', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 5678   100  5120    10240  00:30.50
PS;

    $result = $parser->parse($output, 5678);

    expect($result->isSuccess())->toBeTrue();
    // 30.50 seconds * 100 ticks/sec = 3050 ticks
    expect($result->getValue()->resources->cpuTimes->user)->toBe(3050);
});

it('handles zero CPU time', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 1234     1  1024     2048  00:00:00
PS;

    $result = $parser->parse($output, 1234);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue()->resources->cpuTimes->user)->toBe(0);
    expect($result->getValue()->resources->cpuTimes->system)->toBe(0);
    expect($result->getValue()->resources->cpuTimes->total())->toBe(0);
});

it('converts KB to bytes correctly', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 1234     1  50000   100000  00:01:00
PS;

    $result = $parser->parse($output, 1234);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue()->resources->memoryRssBytes)->toBe(51200000); // 50000 * 1024
    expect($result->getValue()->resources->memoryVmsBytes)->toBe(102400000); // 100000 * 1024
});

it('puts all CPU time in user field', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 1234     1  10240    20480  00:05:00
PS;

    $result = $parser->parse($output, 1234);

    expect($result->isSuccess())->toBeTrue();
    // ps combines user + system, so we put it all in user
    expect($result->getValue()->resources->cpuTimes->user)->toBe(30000); // 300 seconds * 100
    expect($result->getValue()->resources->cpuTimes->system)->toBe(0);
});

it('fails on empty output', function () {
    $parser = new MacOsPsParser;

    $result = $parser->parse('', 1234);

    expect($result->isFailure())->toBeTrue();
    expect($result->getError())->toBeInstanceOf(ParseException::class);
});

it('fails on insufficient output lines', function () {
    $parser = new MacOsPsParser;

    // Only header, no data line
    $output = '  PID  PPID    RSS      VSZ      TIME';

    $result = $parser->parse($output, 1234);

    expect($result->isFailure())->toBeTrue();
    expect($result->getError())->toBeInstanceOf(ParseException::class);
    expect($result->getError()->getMessage())->toContain('Insufficient output lines');
});

it('fails on invalid format with insufficient fields', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID    RSS
 1234     1  10240
PS;

    $result = $parser->parse($output, 1234);

    expect($result->isFailure())->toBeTrue();
    expect($result->getError())->toBeInstanceOf(ParseException::class);
    expect($result->getError()->getMessage())->toContain('insufficient fields');
});

it('handles whitespace variations', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
PID    PPID     RSS    VSZ    TIME
1234      1   10240  20480  00:01:30
PS;

    $result = $parser->parse($output, 1234);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue()->pid)->toBe(1234);
});

it('sets thread count to 1', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 1234     1  10240    20480  00:01:30
PS;

    $result = $parser->parse($output, 1234);

    expect($result->isSuccess())->toBeTrue();
    // ps doesn't provide thread count easily, so we default to 1
    expect($result->getValue()->resources->threadCount)->toBe(1);
});

it('sets openFileDescriptors to zero', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 1234     1  10240    20480  00:01:30
PS;

    $result = $parser->parse($output, 1234);

    expect($result->isSuccess())->toBeTrue();
    // Would need lsof to get this value
    expect($result->getValue()->resources->openFileDescriptors)->toBe(0);
});

it('returns snapshot with timestamp', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 1234     1  10240    20480  00:01:30
PS;

    $result = $parser->parse($output, 1234);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue()->timestamp)->toBeInstanceOf(DateTimeImmutable::class);
});

it('uses ProcessRunner for file descriptor counting', function () {
    $mockRunner = new class implements ProcessRunnerInterface
    {
        public bool $lsofCalled = false;

        public string $lastCommand = '';

        public function execute(string $command): Result
        {
            $this->lastCommand = $command;

            if (str_contains($command, 'lsof')) {
                $this->lsofCalled = true;

                // Field output: one f-record per descriptor, plus the pid
                // record lsof always emits first.
                return Result::success("p1234\nf0\nf1\nf2\nf3\nf4\n");
            }

            return Result::success('');
        }

        public function executeLines(string $command): Result
        {
            return $this->execute($command)->map(fn ($output) => array_values(array_filter(explode("\n", $output), fn ($line) => $line !== '')));
        }

        public function commandExists(string $command): bool
        {
            return true;
        }
    };

    $parser = new MacOsPsParser($mockRunner);

    // A pid that is not this process, so the /dev/fd shortcut does not apply.
    $pid = getmypid() + 1;

    $output = "  PID  PPID    RSS      VSZ      TIME\n {$pid}     1  10240    20480  00:01:30";

    $result = $parser->parse($output, $pid);

    expect($result->isSuccess())->toBeTrue();
    expect($mockRunner->lsofCalled)->toBeTrue();
    expect($mockRunner->lastCommand)->toBe("lsof -p {$pid} -n -P -F f");
    expect($result->getValue()->resources->openFileDescriptors)->toBe(5);
});

it('handles lsof failure gracefully', function () {
    // Create a mock ProcessRunner that simulates lsof failure
    $mockRunner = new class implements ProcessRunnerInterface
    {
        public function execute(string $command): Result
        {
            if (str_starts_with($command, 'lsof')) {
                return Result::failure(new SystemMetricsException('lsof failed'));
            }

            return Result::success('');
        }

        public function executeLines(string $command): Result
        {
            return $this->execute($command)->map(fn ($output) => []);
        }

        public function commandExists(string $command): bool
        {
            return true;
        }
    };

    $parser = new MacOsPsParser($mockRunner);

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 1234     1  10240    20480  00:01:30
PS;

    $result = $parser->parse($output, 1234);

    expect($result->isSuccess())->toBeTrue();
    // When lsof fails, openFileDescriptors should be 0
    expect($result->getValue()->resources->openFileDescriptors)->toBe(0);
});

it('handles large memory values', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID       RSS        VSZ      TIME
 1234     1   2097152    4194304  10:30:45
PS;

    $result = $parser->parse($output, 1234);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue()->resources->memoryRssBytes)->toBe(2147483648); // 2 GB
    expect($result->getValue()->resources->memoryVmsBytes)->toBe(4294967296); // 4 GB
});

it('handles long running process time', function () {
    $parser = new MacOsPsParser;

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 1234     1  10240    20480  999:59:59
PS;

    $result = $parser->parse($output, 1234);

    expect($result->isSuccess())->toBeTrue();
    // 999 hours + 59 minutes + 59 seconds = 3599999 seconds * 100 = 359999900 ticks
    expect($result->getValue()->resources->cpuTimes->user)->toBe(359999900);
});

/**
 * A runner that records what it was asked and answers with canned lsof
 * field output.
 */
final class RecordingLsofRunner implements ProcessRunnerInterface
{
    /** @var list<string> */
    public array $commands = [];

    public function __construct(private readonly string $answer) {}

    public function execute(string $command): Result
    {
        $this->commands[] = $command;

        return Result::success($this->answer);
    }
}

it('counts descriptors, not every file the process has mapped', function () {
    // lsof's default output is every open FILE: the working directory, the
    // executable and each shared library, plus mapped regions. Counting
    // those lines reported 59 for a process with 5 descriptors, and grew
    // with the number of dylibs rather than with open files.
    $runner = new RecordingLsofRunner("p4242\nf0\nf1\nf2\nfcwd\nftxt\nf7\n");

    $parser = new MacOsPsParser($runner);

    $output = <<<'PS'
  PID  PPID    RSS      VSZ      TIME
 4242     1  10240    20480  00:01:30
PS;

    $snapshot = $parser->parse($output, 4242)->getValue();

    // f0, f1, f2 and f7 — not the pid record, and not cwd or txt.
    expect($snapshot->resources->openFileDescriptors)->toBe(4)
        ->and($runner->commands[0])->toContain('-F f');
});

it('reads its own descriptors without spawning a process', function () {
    $runner = new RecordingLsofRunner("p1\nf0\n");

    $parser = new MacOsPsParser($runner);

    $pid = getmypid();

    $output = "  PID  PPID    RSS      VSZ      TIME\n {$pid}     1  10240    20480  00:01:30";

    $snapshot = $parser->parse($output, $pid)->getValue();

    // The whole point: a per-request sampler asks about its own process on
    // every request, and lsof costs ~25ms a call.
    expect($runner->commands)->toBe([])
        ->and($snapshot->resources->openFileDescriptors)->toBeGreaterThan(0);
})->skip(! is_dir('/dev/fd'), '/dev/fd is macOS/BSD only');
