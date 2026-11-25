<?php
declare(strict_types=1);
final class InternationalStudent extends Student {
    private const SURCHARGE_PERCENT = 50.0; // biaya tambahan mahasiswa asing

    public function getType(): string { return 'international'; }

    // overriding abstract method: biaya dasar + 50%
    public function calculateFee(): float {
        return $this->baseFee * (1 + self::SURCHARGE_PERCENT / 100);
    }

    public function summary(): string {
        return parent::summary() . ' [Internasional]';
    }
}
