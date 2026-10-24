<?php

/*
|--------------------------------------------------------------------------
| Categories, spec cleaning rules and supported must-haves
|--------------------------------------------------------------------------
| This file is data, not code, so new categories or spec labels can be
| added without touching the pipeline classes.
|
| specs:         typed fact key => [type, list of raw label aliases]
|                type is "number" (first number in the text, e.g. "8 GB" -> 8),
|                "bool" ("Yes"/"No"), or "text" (lower-cased)
| requirements:  must-haves the search can check.
|                op: ">=", "<=", "==", "contains", "one_of", "true"
|                param: true when the shopper can give their own number
|                       ("at least 12 GB RAM"); value is the default.
|                keywords: phrases the offline AI uses to spot the requirement.
*/

return [

    'earphones' => [
        'name' => 'Earphones',
        'keywords' => ['earbuds', 'earphones', 'earphone', 'tws', 'headphones', 'headphone', 'neckband', 'buds'],
        'specs' => [
            'type' => ['text', ['type', 'headphone type', 'form factor', 'design']],
            'has_mic' => ['bool', ['microphone', 'with microphone', 'with mic', 'mic']],
            'anc' => ['bool', ['active noise cancellation', 'noise cancellation', 'anc']],
            'battery_hours' => ['number', ['battery life', 'playback time', 'total playback', 'battery']],
            'bluetooth_version' => ['number', ['bluetooth version', 'bluetooth']],
            'water_rating' => ['text', ['water resistance', 'ip rating', 'water resistant']],
            'fast_charging' => ['bool', ['fast charging', 'quick charge']],
        ],
        'requirements' => [
            'true_wireless' => ['label' => 'True wireless', 'spec' => 'type', 'op' => 'contains', 'value' => 'true wireless', 'keywords' => ['true wireless', 'tws', 'truly wireless', 'earbuds']],
            'microphone' => ['label' => 'Has a microphone', 'spec' => 'has_mic', 'op' => 'true', 'keywords' => ['mic', 'microphone', 'calls', 'calling']],
            'anc' => ['label' => 'Active noise cancellation', 'spec' => 'anc', 'op' => 'true', 'keywords' => ['anc', 'noise cancellation', 'noise cancelling', 'noise-cancelling']],
            'min_battery_hours' => ['label' => 'Battery of at least :value hours', 'spec' => 'battery_hours', 'op' => '>=', 'value' => 30, 'param' => true, 'keywords' => ['long battery', 'battery life', 'hours battery', 'hr battery']],
            'water_resistant' => ['label' => 'Water resistant', 'spec' => 'water_rating', 'op' => 'contains', 'value' => 'ip', 'keywords' => ['water resistant', 'waterproof', 'sweat', 'ipx', 'gym']],
            'fast_charging' => ['label' => 'Fast charging', 'spec' => 'fast_charging', 'op' => 'true', 'keywords' => ['fast charging', 'quick charge']],
        ],
    ],

    'smartphones' => [
        'name' => 'Smartphones',
        'keywords' => ['phone', 'smartphone', 'mobile', 'smartphones', 'phones'],
        'specs' => [
            'ram_gb' => ['number', ['ram']],
            'storage_gb' => ['number', ['internal storage', 'storage', 'rom']],
            'display_type' => ['text', ['display type', 'display', 'screen type']],
            'network_5g' => ['bool', ['5g', 'supports 5g', '5g support']],
            'battery_mah' => ['number', ['battery capacity', 'battery']],
            'rear_camera_mp' => ['number', ['primary camera', 'rear camera', 'main camera']],
            'charging_w' => ['number', ['charging', 'fast charging', 'charging speed']],
        ],
        'requirements' => [
            'min_ram_gb' => ['label' => 'At least :value GB RAM', 'spec' => 'ram_gb', 'op' => '>=', 'value' => 8, 'param' => true, 'keywords' => ['gb ram', 'ram']],
            'min_storage_gb' => ['label' => 'At least :value GB storage', 'spec' => 'storage_gb', 'op' => '>=', 'value' => 128, 'param' => true, 'keywords' => ['gb storage', 'storage', 'rom']],
            'amoled' => ['label' => 'AMOLED display', 'spec' => 'display_type', 'op' => 'contains', 'value' => 'amoled', 'keywords' => ['amoled', 'oled']],
            '5g' => ['label' => '5G', 'spec' => 'network_5g', 'op' => 'true', 'keywords' => ['5g']],
            'min_battery_mah' => ['label' => 'Battery of at least :value mAh', 'spec' => 'battery_mah', 'op' => '>=', 'value' => 5000, 'param' => true, 'keywords' => ['big battery', 'mah', 'long battery', 'battery backup']],
            'min_camera_mp' => ['label' => 'Main camera of at least :value MP', 'spec' => 'rear_camera_mp', 'op' => '>=', 'value' => 50, 'param' => true, 'keywords' => ['good camera', 'camera', 'mp camera']],
            'fast_charging' => ['label' => 'Fast charging (:value W or more)', 'spec' => 'charging_w', 'op' => '>=', 'value' => 30, 'param' => true, 'keywords' => ['fast charging', 'quick charge']],
        ],
    ],

    'laptops' => [
        'name' => 'Laptops',
        'keywords' => ['laptop', 'laptops', 'notebook', 'ultrabook'],
        'specs' => [
            'ram_gb' => ['number', ['ram']],
            'storage_gb' => ['number', ['ssd capacity', 'storage', 'ssd', 'hdd capacity']],
            'storage_type' => ['text', ['storage type', 'ssd/hdd']],
            'processor' => ['text', ['processor', 'processor name', 'cpu']],
            'dedicated_gpu' => ['bool', ['dedicated graphics', 'dedicated graphic memory', 'graphics card']],
            'weight_kg' => ['number', ['weight']],
            'backlit_keyboard' => ['bool', ['backlit keyboard', 'keyboard backlight']],
            'os' => ['text', ['operating system', 'os']],
        ],
        'requirements' => [
            'min_ram_gb' => ['label' => 'At least :value GB RAM', 'spec' => 'ram_gb', 'op' => '>=', 'value' => 16, 'param' => true, 'keywords' => ['gb ram', 'ram']],
            'ssd' => ['label' => 'SSD storage', 'spec' => 'storage_type', 'op' => 'contains', 'value' => 'ssd', 'keywords' => ['ssd']],
            'min_storage_gb' => ['label' => 'At least :value GB storage', 'spec' => 'storage_gb', 'op' => '>=', 'value' => 512, 'param' => true, 'keywords' => ['gb storage', 'storage']],
            'dedicated_gpu' => ['label' => 'Dedicated graphics', 'spec' => 'dedicated_gpu', 'op' => 'true', 'keywords' => ['gaming', 'graphics card', 'dedicated graphics', 'gpu', 'video editing']],
            'max_weight_kg' => ['label' => 'Weighs :value kg or less', 'spec' => 'weight_kg', 'op' => '<=', 'value' => 1.5, 'param' => true, 'keywords' => ['lightweight', 'light weight', 'portable', 'thin and light']],
            'backlit_keyboard' => ['label' => 'Backlit keyboard', 'spec' => 'backlit_keyboard', 'op' => 'true', 'keywords' => ['backlit']],
        ],
    ],

    'smartwatches' => [
        'name' => 'Smartwatches',
        'keywords' => ['smartwatch', 'smart watch', 'watch', 'smartwatches', 'fitness band', 'fitness tracker'],
        'specs' => [
            'display_type' => ['text', ['display type', 'display']],
            'gps' => ['bool', ['gps', 'built-in gps']],
            'spo2' => ['bool', ['spo2', 'blood oxygen', 'spo2 monitor']],
            'bt_calling' => ['bool', ['bluetooth calling', 'calling']],
            'water_rating' => ['text', ['water resistance', 'water resistant', 'ip rating']],
            'battery_days' => ['number', ['battery life', 'battery']],
        ],
        'requirements' => [
            'amoled' => ['label' => 'AMOLED display', 'spec' => 'display_type', 'op' => 'contains', 'value' => 'amoled', 'keywords' => ['amoled', 'oled']],
            'gps' => ['label' => 'Built-in GPS', 'spec' => 'gps', 'op' => 'true', 'keywords' => ['gps', 'running', 'cycling']],
            'spo2' => ['label' => 'Blood oxygen (SpO2) tracking', 'spec' => 'spo2', 'op' => 'true', 'keywords' => ['spo2', 'blood oxygen']],
            'bt_calling' => ['label' => 'Bluetooth calling', 'spec' => 'bt_calling', 'op' => 'true', 'keywords' => ['calling', 'bluetooth calling', 'take calls']],
            'water_resistant' => ['label' => 'Water resistant', 'spec' => 'water_rating', 'op' => 'contains', 'value' => 'ip', 'keywords' => ['water resistant', 'waterproof', 'swimming', 'swim']],
            'min_battery_days' => ['label' => 'Battery of at least :value days', 'spec' => 'battery_days', 'op' => '>=', 'value' => 7, 'param' => true, 'keywords' => ['long battery', 'battery life', 'days battery']],
        ],
    ],

    'running-shoes' => [
        'name' => 'Running shoes',
        'keywords' => ['running shoes', 'running shoe', 'shoes', 'sneakers', 'trainers', 'joggers'],
        'specs' => [
            'gender' => ['gender', ['ideal for', 'gender']],   // cleaned to men / women / unisex
            'cushioning' => ['text', ['cushioning']],
            'weight_g' => ['number', ['weight']],
            'upper' => ['text', ['upper material', 'outer material', 'material']],
        ],
        'requirements' => [
            // "one_of": the fact must equal one of the listed values ("women" must not count as "men")
            'for_men' => ['label' => 'Made for men', 'spec' => 'gender', 'op' => 'one_of', 'value' => ['men', 'unisex'], 'keywords' => ['for men', "men's", 'mens', 'for my dad', 'for my brother']],
            'for_women' => ['label' => 'Made for women', 'spec' => 'gender', 'op' => 'one_of', 'value' => ['women', 'unisex'], 'keywords' => ['for women', "women's", 'womens', 'for my mom', 'for my sister']],
            'cushioned' => ['label' => 'High cushioning', 'spec' => 'cushioning', 'op' => 'contains', 'value' => 'high', 'keywords' => ['cushioned', 'cushioning', 'soft', 'comfortable', 'long runs']],
            'max_weight_g' => ['label' => 'Weighs :value g or less', 'spec' => 'weight_g', 'op' => '<=', 'value' => 250, 'param' => true, 'keywords' => ['lightweight', 'light weight', 'light']],
            'mesh_upper' => ['label' => 'Breathable mesh upper', 'spec' => 'upper', 'op' => 'contains', 'value' => 'mesh', 'keywords' => ['breathable', 'mesh']],
        ],
    ],

];
