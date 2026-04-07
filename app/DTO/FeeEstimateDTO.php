<?php

namespace App\DTO;

class FeeEstimateDTO
{
    public function __construct(
        public int   $inputsCount,
        public int   $outputsCount,
        public int   $estimatedSizeBytes,
        public int   $satPerByte,
        public int   $feeSats,
        public float $feeBtc,
    )
    {
    }
}

