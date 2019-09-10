<?php

namespace Laravel\Serverless\Kubeless;

class Helper
{
    public static function package_path(...$parts)
    {
        return realpath(__DIR__ . '/../') .
            ($parts ? DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) : '');
    }
}
