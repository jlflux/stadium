<?php
// ============================================================
//  default.php — blank-school template
// ============================================================
//  Returns the starting content structure for a brand-new school.
//  When the Master Admin creates a school, this array is cloned and
//  written to data/schools/{slug}.json, then the school's own admin
//  fills it in. Returning an array (no output) makes this safe to
//  load with include/require.
// ------------------------------------------------------------
return [
    'site' => [
        'school_name'  => 'New School',
        'org_name'     => '',
        'tagline'      => 'Visiting Team Guide',
        'eyebrow'      => 'Visiting Team Guide',
        'hero_top'     => 'Welcome to',
        'hero_em'      => 'Our Stadium',
        'hero_sub'     => '',
        'address'      => '',
        'address_note' => '',
    ],
    'timeline' => [
        'label' => 'Gameday Clock',
        'stops' => [],
    ],
    'welcome' => [
        'kicker'    => 'A Note From Our Athletic Department',
        'heading'   => 'Welcome, Visiting Teams',
        'p1'        => 'Sign in to the admin panel to add your welcome message, gameday contacts, parking, directions, tickets, and more.',
        'p2'        => '',
        'quote'     => '',
        'sig_name'  => '',
        'sig_title' => '',
    ],
    'contacts'   => [],
    'admin_team' => [],
    'parking' => [
        'lede'    => '',
        'cards'   => [],
        'caption' => '',
    ],
    'policies' => [],
    'tickets' => [
        'price'      => '',
        'price_note' => '',
        'items'      => [],
        'url'        => '',
        'cta'        => '',
    ],
    'directions' => [
        'lede'      => '',
        'routes'    => [],
        'map_query' => '',
        'map_label' => '',
    ],
    'gallery' => [
        'lede'   => '',
        'photos' => [],
    ],
    'footer' => [
        'office_phone'  => '',
        'contact_name'  => '',
        'contact_email' => '',
    ],
    'school' => [
        'nav_label'    => '',
        'url'          => '',
        'kicker'       => '',
        'heading'      => '',
        'blurb'        => '',
        'button_label' => '',
    ],
    'media' => [
        'kicker'          => 'For Media & Broadcasts',
        'heading'         => 'Media & Broadcast Information',
        'lede'            => '',
        'cards'           => [],
        'broadcast_links' => [],
    ],
    'section_order' => ['welcome','contacts','parking','policies','media','tickets','directions','school','gallery'],
];
