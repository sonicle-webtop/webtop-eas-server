<?php

namespace lf4php\impl;

use lf4php\CachedClassLoggerFactory;

class ZPLoggerFactory extends CachedClassLoggerFactory {
	const ROOT_LOGGER_NAME = 'ROOT';
	
	public function __construct() {
		parent::__construct(new ZPLoggerAdapter(self::ROOT_LOGGER_NAME));
	}
}
