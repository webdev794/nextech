<?php

// Seller product listing (App\Support\ProductCatalog), modelled on Temu's Add
// product flow and bulk-upload template.
//
//  - attributes: the "product details" a category asks for. Each field is
//    [key, label, type (select | multiselect | text | number), options?,
//    required?, unit?, when?]. `when` makes a field conditional: it only
//    applies — and is only required — once another field has one of the
//    listed values (e.g. battery specs once a battery power source is chosen).
//  - keywords: words in a product name that recommend a category.
//  - variation types a product can vary by (at most two per product).
//    Apparel categories are fixed to Color x Size and need a size chart.
//  - compliance: documents each market asks for, per category. `required`
//    documents must be uploaded before a product can go live; `when` works
//    as for attributes.

$yesNo = ['Yes', 'No'];
$battery = ['Rechargeable battery', 'Replaceable battery'];
$storage = ['16 GB', '32 GB', '64 GB', '128 GB', '256 GB', '512 GB', '1 TB', '2 TB', '4 TB'];
$ram = ['2 GB', '3 GB', '4 GB', '6 GB', '8 GB', '12 GB', '16 GB', '32 GB', '64 GB'];
$wireless = ['when' => ['connectivity' => ['Bluetooth', 'Wi-Fi', 'Cellular 4G', 'Cellular 5G', 'NFC']]];

return [
    'common_attributes' => [
        ['key' => 'model_number', 'label' => 'Model number', 'type' => 'text', 'required' => true],
        ['key' => 'color', 'label' => 'Color', 'type' => 'select', 'options' => ['Black', 'White', 'Silver', 'Gray', 'Blue', 'Red', 'Green', 'Gold', 'Pink', 'Purple', 'Multicolor', 'Other']],
        ['key' => 'material', 'label' => 'Material', 'type' => 'select', 'options' => ['Plastic', 'Aluminum', 'Stainless steel', 'Glass', 'Silicone', 'Leather', 'Fabric', 'Other']],
        ['key' => 'power_source', 'label' => 'Power source', 'type' => 'select', 'options' => ['Rechargeable battery', 'Replaceable battery', 'AC / mains powered', 'USB powered', 'No power needed'], 'required' => true],
        ['key' => 'battery_type', 'label' => 'Battery type', 'type' => 'select', 'options' => ['Lithium-ion', 'Lithium polymer', 'NiMH', 'Alkaline', 'Other'], 'required' => true, 'when' => ['power_source' => $battery]],
        ['key' => 'battery_capacity_mah', 'label' => 'Battery capacity', 'type' => 'number', 'unit' => 'mAh', 'required' => true, 'when' => ['power_source' => $battery]],
        ['key' => 'battery_included', 'label' => 'Battery included', 'type' => 'select', 'options' => $yesNo, 'required' => true, 'when' => ['power_source' => $battery]],
        ['key' => 'connectivity', 'label' => 'Connectivity', 'type' => 'multiselect', 'options' => ['Bluetooth', 'Wi-Fi', 'NFC', 'Cellular 4G', 'Cellular 5G', 'USB-C', 'Lightning', 'Micro-USB', '3.5 mm jack', 'HDMI', 'Ethernet', 'None']],
        ['key' => 'bluetooth_version', 'label' => 'Bluetooth version', 'type' => 'select', 'options' => ['4.2', '5.0', '5.1', '5.2', '5.3', '5.4'], 'when' => ['connectivity' => ['Bluetooth']]],
        ['key' => 'warranty', 'label' => 'Warranty', 'type' => 'select', 'options' => ['No warranty', '6 months', '1 year', '2 years', '3 years or more'], 'required' => true],
        ['key' => 'special_features', 'label' => 'Special features', 'type' => 'text'],
        ['key' => 'package_contents', 'label' => 'What’s in the box', 'type' => 'text'],
    ],

    // Extra product details per category slug.
    'category_attributes' => [
        'mobiles-smartphones' => [
            ['key' => 'storage_capacity', 'label' => 'Storage capacity', 'type' => 'select', 'options' => $storage, 'required' => true],
            ['key' => 'ram', 'label' => 'RAM', 'type' => 'select', 'options' => $ram, 'required' => true],
            ['key' => 'screen_size_in', 'label' => 'Screen size', 'type' => 'number', 'unit' => 'in', 'required' => true],
            ['key' => 'operating_system', 'label' => 'Operating system', 'type' => 'select', 'options' => ['Android', 'iOS', 'Other'], 'required' => true],
            ['key' => 'sim', 'label' => 'SIM', 'type' => 'select', 'options' => ['Single SIM', 'Dual SIM', 'eSIM', 'Dual SIM + eSIM']],
        ],
        'laptops-computers' => [
            ['key' => 'processor', 'label' => 'Processor', 'type' => 'text', 'required' => true],
            ['key' => 'ram', 'label' => 'RAM', 'type' => 'select', 'options' => $ram, 'required' => true],
            ['key' => 'storage_capacity', 'label' => 'Storage capacity', 'type' => 'select', 'options' => $storage, 'required' => true],
            ['key' => 'screen_size_in', 'label' => 'Screen size', 'type' => 'number', 'unit' => 'in'],
            ['key' => 'operating_system', 'label' => 'Operating system', 'type' => 'select', 'options' => ['Windows', 'macOS', 'ChromeOS', 'Linux', 'None'], 'required' => true],
        ],
        'audio-headphones' => [
            ['key' => 'form_factor', 'label' => 'Type', 'type' => 'select', 'options' => ['Earbuds', 'In-ear', 'On-ear', 'Over-ear', 'Speaker', 'Soundbar', 'Microphone'], 'required' => true],
            ['key' => 'noise_cancelling', 'label' => 'Active noise cancelling', 'type' => 'select', 'options' => $yesNo],
        ],
        'smart-watches-wearables' => [
            ['key' => 'screen_size_in', 'label' => 'Screen size', 'type' => 'number', 'unit' => 'in'],
            ['key' => 'water_resistance', 'label' => 'Water resistance', 'type' => 'select', 'options' => ['None', 'Splash resistant (IPX4)', 'IP67', 'IP68', '5 ATM', '10 ATM']],
            ['key' => 'compatible_os', 'label' => 'Works with', 'type' => 'multiselect', 'options' => ['Android', 'iOS']],
        ],
        'cameras-photography' => [
            ['key' => 'resolution_mp', 'label' => 'Resolution', 'type' => 'number', 'unit' => 'MP', 'required' => true],
            ['key' => 'camera_type', 'label' => 'Camera type', 'type' => 'select', 'options' => ['Mirrorless', 'DSLR', 'Action camera', 'Compact', 'Instant', 'Security camera', 'Accessory'], 'required' => true],
        ],
        'televisions' => [
            ['key' => 'screen_size_in', 'label' => 'Screen size', 'type' => 'number', 'unit' => 'in', 'required' => true],
            ['key' => 'display_resolution', 'label' => 'Resolution', 'type' => 'select', 'options' => ['HD', 'Full HD', '4K UHD', '8K'], 'required' => true],
            ['key' => 'display_technology', 'label' => 'Display technology', 'type' => 'select', 'options' => ['LED', 'QLED', 'OLED', 'Mini-LED']],
            ['key' => 'smart_tv', 'label' => 'Smart TV', 'type' => 'select', 'options' => $yesNo],
        ],
        'gaming-consoles-accessories' => [
            ['key' => 'platform', 'label' => 'Platform', 'type' => 'multiselect', 'options' => ['PlayStation', 'Xbox', 'Nintendo Switch', 'PC', 'Mobile'], 'required' => true],
        ],
        'power-banks-chargers' => [
            ['key' => 'output_watts', 'label' => 'Maximum output', 'type' => 'number', 'unit' => 'W', 'required' => true],
            ['key' => 'ports', 'label' => 'Number of ports', 'type' => 'number'],
        ],
        'storage-devices' => [
            ['key' => 'storage_capacity', 'label' => 'Storage capacity', 'type' => 'select', 'options' => $storage, 'required' => true],
            ['key' => 'interface', 'label' => 'Interface', 'type' => 'select', 'options' => ['USB 3.x', 'USB-C', 'Thunderbolt', 'SATA', 'NVMe (M.2)', 'microSD', 'SD'], 'required' => true],
        ],
        'networking-devices' => [
            ['key' => 'wifi_standard', 'label' => 'Wi-Fi standard', 'type' => 'select', 'options' => ['Wi-Fi 5', 'Wi-Fi 6', 'Wi-Fi 6E', 'Wi-Fi 7', 'Not Wi-Fi'], 'required' => true],
        ],
        'home-appliances' => [
            ['key' => 'wattage', 'label' => 'Power', 'type' => 'number', 'unit' => 'W'],
            ['key' => 'voltage', 'label' => 'Voltage', 'type' => 'select', 'options' => ['110–120 V', '220–240 V', '100–240 V (universal)']],
        ],
    ],

    // Name words that point to a category (Getting started -> recommended categories).
    'keywords' => [
        'mobiles-smartphones' => ['phone', 'smartphone', 'iphone', 'galaxy', 'pixel', 'mobile', 'redmi', 'oneplus'],
        'laptops-computers' => ['laptop', 'notebook', 'macbook', 'desktop', 'computer', 'chromebook', 'pc'],
        'audio-headphones' => ['headphone', 'earbud', 'earphone', 'speaker', 'soundbar', 'headset', 'airpods', 'mic', 'microphone'],
        'mobile-accessories' => ['case', 'cover', 'screen protector', 'tempered', 'holder', 'stand', 'selfie'],
        'smart-watches-wearables' => ['watch', 'smartwatch', 'band', 'fitness tracker', 'wearable', 'strap'],
        'cameras-photography' => ['camera', 'lens', 'tripod', 'gopro', 'dslr', 'mirrorless', 'webcam'],
        'televisions' => ['tv', 'television', 'oled', 'qled'],
        'gaming-consoles-accessories' => ['playstation', 'ps5', 'xbox', 'nintendo', 'switch', 'controller', 'gamepad', 'gaming'],
        'home-appliances' => ['vacuum', 'air fryer', 'kettle', 'fan', 'heater', 'purifier', 'blender', 'iron'],
        'computer-accessories' => ['mouse', 'keyboard', 'monitor', 'hub', 'dock', 'webcam', 'mousepad'],
        'power-banks-chargers' => ['charger', 'power bank', 'powerbank', 'adapter', 'cable', 'charging'],
        'storage-devices' => ['ssd', 'hard drive', 'hdd', 'pen drive', 'usb drive', 'flash drive', 'memory card', 'sd card'],
        'networking-devices' => ['router', 'wifi', 'wi-fi', 'mesh', 'extender', 'modem', 'switch'],
        'personal-care-electronics' => ['trimmer', 'shaver', 'hair dryer', 'straightener', 'toothbrush', 'epilator'],
        'kids-and-baby-tech' => ['kids', 'baby', 'monitor', 'toy'],
        'office-electronics' => ['printer', 'scanner', 'projector', 'calculator', 'shredder'],
        'smart-home' => ['smart plug', 'smart bulb', 'doorbell', 'alexa', 'echo', 'google home', 'smart lock'],
        'health-and-fitness-tech' => ['scale', 'blood pressure', 'oximeter', 'massager', 'thermometer'],
        'car-electronics' => ['car', 'dash cam', 'dashcam', 'car charger', 'car stereo'],
    ],

    'variation_types' => ['Color', 'Size', 'Storage capacity', 'RAM', 'Capacity', 'Style', 'Pattern', 'Material', 'Compatible model', 'Length', 'Wattage', 'Bundle'],
    'max_variation_levels' => 2,

    // Apparel: variation theme fixed to Color x Size, size chart required.
    'apparel_categories' => [],
    'size_families' => [
        'Alpha' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'],
        'Numeric' => ['0', '2', '4', '6', '8', '10', '12', '14', '16'],
    ],
    'sub_size_families' => ['Regular', 'Petite', 'Plus', 'Tall'],
    'size_chart_measurements' => ['Chest-Product', 'Length-Product', 'Sleeve-Product', 'Bust-Body', 'Waist-Body', 'Hip-Body'],

    'handling_days' => [1, 2, 3, 4, 5, 7, 10],

    'compliance' => [
        'US' => [
            'default' => [
                ['key' => 'product_guide', 'label' => 'Product guide or user manual'],
                ['key' => 'battery_safety', 'label' => 'Battery safety document (UN38.3 test summary / SDS)', 'required' => true, 'when' => ['power_source' => $battery]],
                ['key' => 'fcc', 'label' => 'FCC ID grant or Supplier’s Declaration of Conformity', 'required' => true] + $wireless,
            ],
            'power-banks-chargers' => [['key' => 'ul_safety', 'label' => 'Safety test report (UL 2056 / UL 62368-1)', 'required' => true]],
            'kids-and-baby-tech' => [['key' => 'cpc', 'label' => 'Children’s Product Certificate (CPC)', 'required' => true]],
        ],
        'IN' => [
            'default' => [
                ['key' => 'product_guide', 'label' => 'Product guide or user manual'],
                ['key' => 'battery_safety', 'label' => 'Battery safety document (UN38.3 test summary / SDS)', 'required' => true, 'when' => ['power_source' => $battery]],
                ['key' => 'wpc_eta', 'label' => 'WPC Equipment Type Approval (ETA)', 'required' => true] + $wireless,
            ],
            'power-banks-chargers' => [['key' => 'bis_crs', 'label' => 'BIS registration certificate (CRS)', 'required' => true]],
            'mobiles-smartphones' => [['key' => 'bis_crs', 'label' => 'BIS registration certificate (CRS)', 'required' => true]],
            'laptops-computers' => [['key' => 'bis_crs', 'label' => 'BIS registration certificate (CRS)', 'required' => true]],
            'smart-watches-wearables' => [['key' => 'bis_crs', 'label' => 'BIS registration certificate (CRS)', 'required' => true]],
        ],
    ],
];
