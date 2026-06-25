<?php

namespace App\Services\ResultVerify;

use App\Services\ResultVerify\ResultGates\NabtebResult;
use App\Services\ResultVerify\ResultGates\NECOResult;
use App\Services\ResultVerify\ResultGates\NbaisResult;
use App\Services\ResultVerify\ResultGates\WAECResult;
use InvalidArgumentException;

class ResultFactory
{
    protected array $boards = [
        'nabteb' => NabtebResult::class,
        'neco'   => NECOResult::class,
        'nbais'  => NbaisResult::class,
        'waec'   => WAECResult::class,
    ];

    public function create(string $board): ResultInterface
    {
        $key = strtolower($board);

        if (!isset($this->boards[$key])) {
            throw new InvalidArgumentException("Unsupported result board: {$board}");
        }

        $class = $this->boards[$key];
        return app($class);
    }

    public function register(string $name, string $class): void
    {
        $this->boards[strtolower($name)] = $class;
    }

    public function registered(): array
    {
        return array_keys($this->boards);
    }
}
