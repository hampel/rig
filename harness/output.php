<?php

/**
 * Exercise: every shape of output the rig can produce, to look at.
 *
 * The rig's own harness, which is the only honest way to work on Io: whether output
 * reads well is not a thing a test can tell you. The unit tests assert that the escape
 * codes come out; this shows you whether the result is worth reading.
 *
 * @var Hampel\Rig\Io $io
 */

use Hampel\Rig\Io;

/** A value object of the shape a package returns, to show how one renders. */
class Money
{
    public function __construct(private string $amount)
    {
    }

    public function __toString(): string
    {
        return $this->amount;
    }
}

$io->title('rig · output');

$io->info('  info - context, not an outcome');
$io->success('success - it did the thing');
$io->warn('warn - worth knowing, not fatal');
$io->error('error - it did not work');

$io->line();
$io->info('  values, as an exercise usually reports them');

$io->value('string', 'text');
$io->value('int', 42);
$io->value('float', 1.5);
$io->value('bool', true);
$io->value('null', null);
$io->value('array', ['a' => 1, 'b' => [2, 3]]);
$io->value('list', [2, 3]);
$io->value('empty', []);
$io->value('deep', ['a' => ['b' => ['c' => ['d' => 'past the cap']]]]);
$io->value('long', range(1, 14));
$io->value('object', new Io());
$io->value('stringable', new Money('$5.00'));

$io->line();
$io->info('  attempt - the outcome of doing something real');

$io->attempt('something that works', fn (): string => 'the return value');
$io->attempt('something that throws', function (): never {
    throw new RuntimeException('the message', 7);
});

$io->line();
$io->value('php', PHP_VERSION);
$io->value('decorated', $io->isDecorated());
