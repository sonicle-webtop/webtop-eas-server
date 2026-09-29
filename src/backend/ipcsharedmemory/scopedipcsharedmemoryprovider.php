<?php

/**
 * IPC shared memory provider with support for a configurable numeric scope.
 * 
 * This provider wraps the standard IpcSharedMemoryProvider and shifts the
 * numeric IPC type by the value of IPC_PROVIDER_TYPE_OFFSET, when defined.
 * 
 * NOTE:
 * InterProcessData detects the native shared memory provider using a strict
 * class-name comparison against 'IpcSharedMemoryProvider':
 *
 *   $this->provider_class !== 'IpcSharedMemoryProvider'
 *
 * Because of this, subclasses of IpcSharedMemoryProvider are incorrectly
 * treated as generic string-keyed IPC providers and may receive composite
 * types such as:
 *
 *   "<deviceid>-<type>"
 *
 * SysV IPC functions only accept numeric keys, therefore this provider
 * normalizes composite types by extracting the trailing numeric identifier
 * before applying the configured IPC_PROVIDER_TYPE_OFFSET.
 *
 * This preserves the original behavior of IpcSharedMemoryProvider while
 * allowing multiple PHP-FPM pools or Z-Push instances to coexist without
 * sharing the same SysV IPC keys.
 * 
 * Example:
 *
 *   define('IPC_PROVIDER', '\ScopedIpcSharedMemoryProvider');
 *   define('IPC_PROVIDER_TYPE_OFFSET', 2600);
 *
 *   type 20   -> 2620
 *   type 1337 -> 3937
 *
 * @see IpcSharedMemoryProvider
 * @author malbinola
 */
class ScopedIpcSharedMemoryProvider extends IpcSharedMemoryProvider {
	
	/**
	 * Creates a scoped shared memory provider instance.
	 *
	 * The given IPC type is normalized to a numeric SysV IPC key. If the type is
	 * already numeric, it is used as-is. If it is a composite string ending with
	 * "-<number>", the trailing number is extracted.
	 *
	 * When IPC_PROVIDER_TYPE_OFFSET is defined and numeric, it is added to the
	 * normalized type before delegating to IpcSharedMemoryProvider.
	 *
	 * @param int|string $type      IPC type/key or composite InterProcessData type.
	 * @param int        $allocate  Shared memory allocation size.
	 * @param string     $class     Class name used by the parent provider.
	 * @param string     $serverKey Server-specific key used by the parent provider.
	 *
	 * @throws FatalException If the IPC type cannot be converted to a numeric key.
	 */
	public function __construct($type, $allocate, $class, $serverKey) {
		$finalType = intval($type);
		$offset = defined('IPC_PROVIDER_TYPE_OFFSET') ? IPC_PROVIDER_TYPE_OFFSET : 0;
		if (!is_numeric($offset)) {
			$offset = 0;
		}
		
		$originalType = $type;
		if (is_numeric($type)) {
			$baseType = intval($type);
		} else if (preg_match('/-(\d+)$/', $type, $matches)) {
			$baseType = intval($matches[1]);
		} else {
			throw new FatalException(sprintf("Invalid IPC shared memory type '%s': numeric value expected", $type));
		}
		
		$finalType = $baseType + intval($offset);
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("Initializing IPC provider type with an extra offset (+%s): %s -> %s", $offset, $originalType, $finalType));
		parent::__construct($finalType, $allocate, $class, $serverKey);
	}
}
