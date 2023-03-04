<?php

namespace App\Domain;

class ProgressBar
{
    private function __construct(
        private readonly string $label,
        private readonly string $quantityDescription,
        private readonly float $percentage
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getQuantityDescription(): string
    {
        return $this->quantityDescription;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }

    public static function fromValues(
        string $label,
        string $quantityDescription,
        float $percentage
    ): self {
        return new self(
            $label,
            $quantityDescription,
            $percentage,
        );
    }
}
