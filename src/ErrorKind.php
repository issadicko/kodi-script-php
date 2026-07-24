<?php

declare(strict_types=1);

namespace KodiScript;

/**
 * Classifies why script execution failed, for programmatic handling.
 * Mirrors Go's kodi.ErrorKind.
 */
enum ErrorKind: string
{
    case None = 'none';
    case Parse = 'parse';
    case Runtime = 'runtime';
    case Timeout = 'timeout';
    case MaxOperations = 'max_operations';
}
