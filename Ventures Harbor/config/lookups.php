<?php

$VH_INDUSTRIES = [
    'Automotive',
    'Food & Beverage',
    'Technology',
    'Infrastructure',
    'Manufacturing',
    'Education',
    'Healthcare',
    'Logistics',
    'Agriculture',
    'Real Estate',
    'Retail & E-commerce',
    'Fashion & Apparel',
    'Beauty & Wellness',
    'Fitness & Sports',
    'Media & Entertainment',
    'Travel & Tourism',
    'Hospitality',
    'Financial Services',
    'Consulting & Services',
    'Events & Weddings',
    'Printing & Packaging',
    'Renewable Energy',
    'Handicrafts',
    'Pet Care',
];

$VH_STATES = [
    'Andhra Pradesh',
    'Arunachal Pradesh',
    'Assam',
    'Bihar',
    'Chhattisgarh',
    'Goa',
    'Gujarat',
    'Haryana',
    'Himachal Pradesh',
    'Jharkhand',
    'Karnataka',
    'Kerala',
    'Madhya Pradesh',
    'Maharashtra',
    'Manipur',
    'Meghalaya',
    'Mizoram',
    'Nagaland',
    'Odisha',
    'Punjab',
    'Rajasthan',
    'Sikkim',
    'Tamil Nadu',
    'Telangana',
    'Tripura',
    'Uttar Pradesh',
    'Uttarakhand',
    'West Bengal',
    'Andaman and Nicobar Islands',
    'Chandigarh',
    'Dadra and Nagar Haveli and Daman and Diu',
    'Delhi',
    'Jammu and Kashmir',
    'Ladakh',
    'Lakshadweep',
    'Puducherry',
];

$VH_STATE_LABELS = [
    'Chandigarh' => 'Chandigarh (UT)',
    'Delhi'      => 'Delhi (NCT)',
    'Puducherry' => 'Puducherry (UT)',
];

$VH_CITIES = [
    'Agra',
    'Ahmedabad',
    'Ajmer',
    'Aligarh',
    'Amritsar',
    'Aurangabad',
    'Bangalore',
    'Bareilly',
    'Bhopal',
    'Bhubaneswar',
    'Bikaner',
    'Chandigarh',
    'Chennai',
    'Coimbatore',
    'Cuttack',
    'Dehradun',
    'Delhi',
    'Dhanbad',
    'Durgapur',
    'Faridabad',
    'Ghaziabad',
    'Gorakhpur',
    'Guntur',
    'Gurugram',
    'Guwahati',
    'Gwalior',
    'Hubli',
    'Hyderabad',
    'Indore',
    'Jabalpur',
    'Jaipur',
    'Jalandhar',
    'Jammu',
    'Jamshedpur',
    'Jodhpur',
    'Kanpur',
    'Kochi',
    'Kolhapur',
    'Kolkata',
    'Kota',
    'Kozhikode',
    'Lucknow',
    'Ludhiana',
    'Madurai',
    'Mangaluru',
    'Meerut',
    'Mumbai',
    'Mysuru',
    'Nagpur',
    'Nashik',
    'Navi Mumbai',
    'Noida',
    'Panaji',
    'Patna',
    'Puducherry',
    'Pune',
    'Raipur',
    'Rajkot',
    'Ranchi',
    'Rourkela',
    'Salem',
    'Shimla',
    'Siliguri',
    'Solapur',
    'Srinagar',
    'Surat',
    'Thane',
    'Thiruvananthapuram',
    'Tiruchirappalli',
    'Tirupati',
    'Udaipur',
    'Ujjain',
    'Vadodara',
    'Varanasi',
    'Vijayawada',
    'Visakhapatnam',
    'Warangal',
];

// The marketplace's coarse "what kind of thing is this?" axis, shown as the
// filter pills on the homepage and Browse. Deliberately separate from
// $VH_INDUSTRIES: industry stays a 24-value list a founder picks from, while
// this is the five-way split the client's repositioning asks visitors to shop
// by. Slugs are what ventures.asset_class stores (migration step 49); labels
// are the only place the pill wording lives, so both renderers agree.
$VH_ASSET_CLASSES = [
    'real_estate'    => 'Real Estate',
    'businesses'     => 'Businesses',
    'ip_royalties'   => 'IP & Royalties',
    'franchise'      => 'Franchises',
    'infrastructure' => 'Infrastructure',
];

// Which class a listing falls into when nobody chose one. Mirrors the back-fill
// in migration step 49 so a row written by the API and a row fixed up by the
// migration can never disagree. Anything unmapped is a business, which is the
// only thing the platform accepted before asset classes existed.
if (!function_exists('vh_asset_class_for_industry')) {
    function vh_asset_class_for_industry($industry) {
        $map = [
            'real estate'    => 'real_estate',
            'infrastructure' => 'infrastructure',
            'renewable energy' => 'infrastructure',
        ];
        return $map[strtolower(trim((string)$industry))] ?? 'businesses';
    }
}

// Normalise anything arriving from a request or an older row. Returns '' for a
// value that is not a real class, so callers can tell "not chosen" from "chosen".
if (!function_exists('vh_normalize_asset_class')) {
    function vh_normalize_asset_class($value) {
        global $VH_ASSET_CLASSES;
        $v = strtolower(trim((string)$value));
        return isset($VH_ASSET_CLASSES[$v]) ? $v : '';
    }
}

// How many classes one listing may claim. Enough for a genuinely cross-category
// listing (land being developed as infrastructure) without letting someone tick
// everything and appear under every pill, which would make the filters useless.
if (!defined('VH_ASSET_CLASS_MAX')) define('VH_ASSET_CLASS_MAX', 3);

// A founder-named class becomes a slug so it stores and compares like the
// canonical ones. FIND_IN_SET is the filter, so a slug may never contain a comma.
if (!function_exists('vh_asset_class_slug')) {
    function vh_asset_class_slug($label) {
        global $VH_ASSET_CLASSES;
        $raw = trim((string)$label);
        // A canonical label typed by hand must land on its canonical slug, or
        // "IP & Royalties" becomes ip_and_royalties and stops matching the pill.
        foreach ($VH_ASSET_CLASSES as $slug => $lbl) {
            if (strcasecmp($raw, $lbl) === 0 || strcasecmp($raw, $slug) === 0) return $slug;
        }
        $s = strtolower($raw);
        $s = preg_replace('/&/', ' and ', $s);
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        $s = trim($s, '_');
        return substr($s, 0, 40);
    }
}

// The stored value is a comma-separated list; one class is simply a list of one.
// Returns slugs in stored order — the first is the listing's primary class, which
// is what a card shows when it has room for only one.
if (!function_exists('vh_parse_asset_classes')) {
    function vh_parse_asset_classes($stored) {
        $out = [];
        foreach (explode(',', (string)$stored) as $part) {
            $slug = vh_asset_class_slug($part);
            if ($slug !== '' && !in_array($slug, $out, true)) $out[] = $slug;
        }
        return $out;
    }
}

// Label for display: canonical classes read from $VH_ASSET_CLASSES so the wording
// lives in one place; a custom slug is turned back into words.
if (!function_exists('vh_asset_class_label')) {
    function vh_asset_class_label($slug) {
        global $VH_ASSET_CLASSES;
        $s = vh_asset_class_slug($slug);
        if (isset($VH_ASSET_CLASSES[$s])) return $VH_ASSET_CLASSES[$s];
        return ucwords(str_replace('_', ' ', $s));
    }
}

// Accepts an array (the form posts one) or a comma string, and returns the value
// to store. Custom classes are kept — the client asked for them — but the count
// is capped, so a crafted request cannot claim every pill.
if (!function_exists('vh_normalize_asset_class_list')) {
    function vh_normalize_asset_class_list($input) {
        $parts = is_array($input) ? $input : explode(',', (string)$input);
        $out = [];
        foreach ($parts as $p) {
            $slug = vh_asset_class_slug($p);
            if ($slug === '' || in_array($slug, $out, true)) continue;
            $out[] = $slug;
            if (count($out) >= VH_ASSET_CLASS_MAX) break;
        }
        return implode(',', $out);
    }
}
