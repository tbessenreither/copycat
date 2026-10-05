<?php

declare(strict_types=1);

namespace Tbessenreither\Copycat\Service;

/**
 * Per-package buffer for operation records produced during a Copycat run.
 *
 * Each op method on {@see Copycat} records a single entry here instead of
 * printing directly, so that presentation can be deferred to the end of the
 * package (see {@see OperationPrinter}). Consecutive records that target the
 * same file/directory coalesce on {@see self::drain()} into a single group,
 * which is what lets multiple `envAdd` calls to `.env.example` render as one
 * summary line instead of one line per call.
 *
 * The recorder is intentionally dumb: no I/O, no console access, no verbosity
 * awareness. All layout decisions live in {@see OperationPrinter}.
 *
 * Group shapes (the associative arrays returned by {@see self::drain()}):
 *
 *   env     ['type' => 'env',     'file' => string, 'added' => string[],
 *            'replaced' => string[], 'skipped' => string[]]
 *   copy    ['type' => 'copy',    'targetDir' => string, 'added' => string[],
 *            'skipped' => string[]]
 *   ignore  ['type' => 'ignore',  'file' => string, 'added' => string[],
 *            'skipped' => string[]]
 *   json    ['type' => 'json',    'file' => string, 'path' => string,
 *            'changed' => bool]
 *   bundle  ['type' => 'bundle',  'file' => string, 'identifier' => string,
 *            'changed' => bool]
 *   service ['type' => 'service', 'file' => string, 'identifier' => string,
 *            'changed' => bool]
 */
final class OperationRecorder
{
    /**
     * Ordered list of raw op records, in the order they were recorded.
     * Cleared by {@see self::drain()}.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $ops = [];

    /**
     * @param string[] $added
     * @param string[] $replaced
     * @param string[] $skipped
     */
    public function recordEnv(string $file, array $added, array $replaced, array $skipped): void
    {
        $this->ops[] = [
            'type' => 'env',
            'file' => $file,
            'added' => $added,
            'replaced' => $replaced,
            'skipped' => $skipped,
        ];
    }

    public function recordCopy(string $targetDir, string $sourceName, bool $wasCopied): void
    {
        $this->ops[] = [
            'type' => 'copy',
            'targetDir' => $targetDir,
            'added' => $wasCopied ? [$sourceName] : [],
            'skipped' => $wasCopied ? [] : [$sourceName],
        ];
    }

    /**
     * @param string[] $added
     * @param string[] $skipped
     */
    public function recordIgnore(string $file, array $added, array $skipped): void
    {
        $this->ops[] = [
            'type' => 'ignore',
            'file' => $file,
            'added' => $added,
            'skipped' => $skipped,
        ];
    }

    public function recordJson(string $file, string $path, bool $changed): void
    {
        $this->ops[] = [
            'type' => 'json',
            'file' => $file,
            'path' => $path,
            'changed' => $changed,
        ];
    }

    public function recordBundle(string $file, string $identifier, bool $changed): void
    {
        $this->ops[] = [
            'type' => 'bundle',
            'file' => $file,
            'identifier' => $identifier,
            'changed' => $changed,
        ];
    }

    public function recordService(string $file, string $identifier, bool $changed): void
    {
        $this->ops[] = [
            'type' => 'service',
            'file' => $file,
            'identifier' => $identifier,
            'changed' => $changed,
        ];
    }

    /**
     * Return all recorded ops as coalesced groups and reset the buffer.
     *
     * Coalescence key is `type:target` — ops that share the key merge into the
     * position of the first occurrence, preserving the order in which target
     * files first appeared. `changed` flags OR together; list fields
     * (`added`, `replaced`, `skipped`) concatenate.
     *
     * @return array<int, array<string, mixed>>
     */
    public function drain(): array
    {
        $groups = $this->coalesce($this->ops);
        $this->ops = [];

        return $groups;
    }

    /**
     * @param array<int, array<string, mixed>> $ops
     * @return array<int, array<string, mixed>>
     */
    private function coalesce(array $ops): array
    {
        /** @var array<string, int> $keyToIndex */
        $keyToIndex = [];
        $groups = [];

        foreach ($ops as $op) {
            $key = $this->coalesceKey($op);

            if (isset($keyToIndex[$key])) {
                $idx = $keyToIndex[$key];
                $groups[$idx] = $this->mergeGroup($groups[$idx], $op);
            } else {
                $keyToIndex[$key] = count($groups);
                $groups[] = $op;
            }
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $op
     */
    private function coalesceKey(array $op): string
    {
        return match ($op['type']) {
            'env' => 'env:' . $op['file'],
            'copy' => 'copy:' . $op['targetDir'],
            'ignore' => 'ignore:' . $op['file'],
            // json/bundle/service are keyed by their identifier so consecutive
            // identical calls (unusual, but possible) collapse into one line.
            'json' => 'json:' . $op['file'] . ':' . $op['path'],
            'bundle' => 'bundle:' . $op['file'] . ':' . $op['identifier'],
            'service' => 'service:' . $op['file'] . ':' . $op['identifier'],
            default => 'unknown:' . spl_object_hash((object) $op),
        };
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @return array<string, mixed>
     */
    private function mergeGroup(array $a, array $b): array
    {
        foreach (['added', 'replaced', 'skipped'] as $field) {
            if (isset($b[$field])) {
                $a[$field] = array_merge($a[$field] ?? [], $b[$field]);
            }
        }
        if (isset($b['changed'])) {
            $a['changed'] = ($a['changed'] ?? false) || $b['changed'];
        }

        return $a;
    }
}
