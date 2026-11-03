
<?php

return [
    'chunk_size' => 500,
    'batch_size' => 500,
    'queue' => 'excel-imports',
    'max_file_kb' => 20480,

    'header_row' => 5,
    'first_data_row' => 7,

    'first_data_rows' => [
        'equipment_master' => 2,
    ],

    'equipment_master_stage_id' => 50,

    'process_ids' => [
        'master_instrument' => 1,
        'field_instrument_master' => 2,
        'spare_instrument_master' => 4,
        'equipment_master' => 10,
    ],

    'tables' => [
        'master_instrument' => 'instrument_masters',
        'field_instrument_master' => 'field_instrument_masters',
        'spare_instrument_master' => 'spare_instrument_masters',
        'equipment_master' => 'equipment_masters',
    ],
];
