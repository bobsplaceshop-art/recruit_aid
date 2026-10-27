-- ============================================================
-- Corporate Aid — seed currencies
-- ============================================================
-- Common currencies for recruitment operations. The customer
-- can add more via the admin UI (once built).
-- ============================================================

INSERT INTO `core_currencies` (`code`, `name`, `symbol`, `decimal_places`, `is_active`) VALUES
    ('KES', 'Kenyan Shilling',        'KSh', 2, 1),
    ('USD', 'US Dollar',              '$',   2, 1),
    ('GBP', 'British Pound',          '£',   2, 1),
    ('EUR', 'Euro',                   '€',   2, 1),
    ('ZAR', 'South African Rand',     'R',   2, 1),
    ('UGX', 'Ugandan Shilling',       'USh', 0, 1),
    ('TZS', 'Tanzanian Shilling',     'TSh', 0, 1),
    ('NGN', 'Nigerian Naira',         '₦',   2, 1),
    ('GHS', 'Ghanaian Cedi',          '₵',   2, 1),
    ('AED', 'UAE Dirham',             'د.إ', 2, 1),
    ('INR', 'Indian Rupee',           '₹',   2, 1)
ON DUPLICATE KEY UPDATE
    `name`           = VALUES(`name`),
    `symbol`         = VALUES(`symbol`),
    `decimal_places` = VALUES(`decimal_places`);
