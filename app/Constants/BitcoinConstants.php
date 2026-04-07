<?php

namespace App\Constants;

class BitcoinConstants
{
    /**
     * Number of satoshis in one Bitcoin.
     */
    public const SATOSHIS_PER_BTC = 100_000_000;

    /**
     * Estimated size in bytes of a single P2WPKH input when building a transaction.
     * Used for fee calculation: inputs_count * INPUT_BYTES.
     */
    public const INPUT_BYTES = 68;

    /**
     * Estimated size in bytes of a single P2WPKH output when building a transaction.
     * Used for fee calculation: outputs_count * OUTPUT_BYTES.
     */
    public const OUTPUT_BYTES = 31;

    /**
     * Fixed base overhead in bytes common to every transaction
     * (version, locktime, input/output count varints).
     */
    public const TX_BASE_BYTES = 10;

    /**
     * Default number of outputs assumed when estimating fee:
     * one output to the recipient and one change output.
     */
    public const DEFAULT_OUTPUTS_COUNT = 2;

    /**
     * Dust threshold in satoshis. Outputs below this value are considered
     * uneconomical to spend and are folded into the fee instead.
     */
    public const DUST_THRESHOLD_SATS = 546;

    /**
     * Block-confirmation target used when querying the mempool fee-estimates API.
     * "3" means the fee rate needed to confirm within ~3 blocks.
     */
    public const FEE_ESTIMATE_BLOCK_TARGET = '3';

    /**
     * Fallback fee rate (sat/byte) used when the fee-estimates API returns
     * no value for the chosen block target.
     */
    public const DEFAULT_FEE_RATE_SAT_PER_BYTE = 10;

    /**
     * Maximum number of transactions returned per page by the explorer API.
     * Pagination continues while the chunk size equals this value.
     */
    public const TX_PAGE_SIZE = 25;
}
