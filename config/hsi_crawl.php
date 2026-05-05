<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | Used to resolve relative navigation/footer links.
    |
    */
    'base_url' => env('HSI_CRAWL_BASE_URL', 'https://hsi.com'),

    /*
    |--------------------------------------------------------------------------
    | Seed URLs (Phase 1)
    |--------------------------------------------------------------------------
    |
    | Manually seeded from primary main-nav + footer links.
    | Keep these focused on evergreen "primary" pages.
    |
    */
    'seed_urls' => [
        '/',

        // Main nav
        '/solutions/hsi-intelligence',
        '/solutions/ehs-environmental-health-and-safety/sky-ai-assistant',

        '/solutions/ehs-environmental-health-and-safety',
        '/solutions/ehs-environmental-health-and-safety/worker-safety',
        '/solutions/ehs-environmental-health-and-safety/incident-management',
        '/solutions/ehs-environmental-health-and-safety/audit-management',
        '/solutions/ehs-environmental-health-and-safety/checklist-inspections',
        '/solutions/ehs-environmental-health-and-safety/compliance-management',
        '/solutions/ehs-environmental-health-and-safety/safety-meetings-management',
        '/solutions/ehs-environmental-health-and-safety/observations-management',
        '/solutions/safety-training',
        '/solutions/ehs-environmental-health-and-safety/worker-wellness',
        '/solutions/ehs-environmental-health-and-safety/ergonomics-management',
        '/solutions/ehs-environmental-health-and-safety/injury-claims-management',
        '/solutions/ehs-environmental-health-and-safety/control-of-work',
        '/solutions/ehs-environmental-health-and-safety/quality-management',
        '/solutions/ehs-environmental-health-and-safety/permit-to-work',
        '/solutions/ehs-environmental-health-and-safety/contractor-management',
        '/solutions/ehs-environmental-health-and-safety/project-management',
        '/solutions/ehs-environmental-health-and-safety/obligations-management',
        '/solutions/chemical-management',
        '/solutions/chemical-management/sds-management',
        '/solutions/chemical-management/on-site-inventory',
        '/solutions/chemical-management/sds-authoring',
        '/solutions/chemical-management/24-hour-hotline',
        '/solutions/ehs-environmental-health-and-safety/operational-risk',
        '/solutions/ehs-environmental-health-and-safety/risk-management',
        '/solutions/ehs-environmental-health-and-safety/vendor-supplier-management',
        '/solutions/ehs-environmental-health-and-safety/change-management',
        '/solutions/ehs-environmental-health-and-safety/job-safety-analysis-job-hazard-analysis',
        '/solutions/esg-environmental-social-governance',
        '/solutions/ehs-environmental-health-and-safety/greenhouse-gases',
        '/solutions/esg-environmental-social-governance/esg-reporting',
        '/solutions/keyesg-partnership',
        '/solutions/training-and-employee-development',
        '/courses',
        '/solutions/industrial-skills-training',
        '/solutions/employee-training-and-development',
        '/solutions/learning-management-system',
        '/solutions/osha-10-30-online-training',
        '/solutions/msha/msha-overview',
        '/solutions/cpr-aed-first-aid-training',
        '/solutions/active-shooter-training',
        '/solutions/first-responder-continuing-education-training',

        '/solutions/compliance-and-safety-software',
        '/solutions/ehs-environmental-health-and-safety/platform-overview',
        '/solutions/ehs-environmental-health-and-safety/ehs-mobile-features',
        '/solutions/ehs-environmental-health-and-safety/cloud-no-code-technology',
        '/solutions/ehs-environmental-health-and-safety/ehs-configuration',
        '/solutions/ehs-environmental-health-and-safety/ehs-data-visibility',
        '/solutions/ehs-environmental-health-and-safety/why-hsi',

        '/industries/industry-overview',
        '/industries/food',
        '/industries/automotive',
        '/industries/construction',
        '/industries/elementary-and-secondary-schools',
        '/industries/energy-and-utilities',
        '/industries/banking',
        '/industries/municipalities',
        '/industries/healthcare',
        '/industries/higher-education',
        '/industries/manufacturing',
        '/industries/mining',
        '/industries/oil-and-gas',
        '/industries/telecommunications',
        '/industries/transportation',

        '/services/customer-success',
        '/services/support-services',
        '/services/implementation-services',

        '/resources',
        '/blog',
        '/resources/case-study',
        '/resources/white-paper',
        '/resources/webinar',
        '/resources/safety-tip',
        '/resources/supervisor-safety-tip',
        '/podcast/accidentalSafetyPro',
        '/resources/checklist-template-toolkit',
        '/resources/guides',

        '/about-hsi',
        '/news',
        '/partnerships',
        '/contact',
        '/legal',

        // Footer bottom
        '/privacy',
        '/terms',

        // Footer extras
        '/elearning-pricing',
    ],
];

