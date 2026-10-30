<?php

namespace WT;

use Monolog\LogRecord;

/**
 * Monolog has no TRACE level: Log writes TRACE messages as DEBUG records
 * marked in their context. This formatter prints them with the TRACE label
 * and removes the marker, so the output looks like a native level.
 */
class LogLineFormatter extends \Monolog\Formatter\LineFormatter {

	protected function normalizeRecord(LogRecord $record): array {
		$normalized = parent::normalizeRecord($record);
		if (!empty($normalized['context'][Log::TRACE_MARKER])) {
			$normalized['level_name'] = 'TRACE';
			unset($normalized['context'][Log::TRACE_MARKER]);
		}
		return $normalized;
	}
}
