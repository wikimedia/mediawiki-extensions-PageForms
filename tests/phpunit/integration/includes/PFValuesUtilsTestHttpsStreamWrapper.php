<?php

class PFValuesUtilsTestHttpsStreamWrapper {
	public $context;

	private static string $responseBody = '';
	private static string $lastOpenedPath = '';
	private static int $position = 0;

	public static function setResponseBody( string $responseBody ): void {
		self::$responseBody = $responseBody;
	}

	public static function getLastOpenedPath(): string {
		return self::$lastOpenedPath;
	}

	public static function reset(): void {
		self::$responseBody = '';
		self::$lastOpenedPath = '';
		self::$position = 0;
	}

	// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	public function stream_open( $path, $mode, $options, &$opened_path ): bool {
		unset( $mode, $options, $opened_path );
		self::$lastOpenedPath = $path;
		self::$position = 0;
		return true;
	}

	public function stream_read( $count ): string {
		$chunk = substr( self::$responseBody, self::$position, $count );
		self::$position += strlen( $chunk );
		return $chunk;
	}

	public function stream_eof(): bool {
		return self::$position >= strlen( self::$responseBody );
	}

	public function stream_stat(): array {
		return [];
	}

	// phpcs:enable
}
