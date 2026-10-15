<?php

namespace App\Support;

class KycQuestionnaire
{
    public static function sections(bool $business): array
    {
        return [
            ['title' => 'Business details', 'fields' => [
                self::field('registered_name', 'Registered name', required: true),
                self::field('registration_number', 'Registration number (CAC)', required: $business),
                self::field('office_address', 'Office address', 'textarea', true),
                self::field('country', 'Country of incorporation', required: true),
                [...self::field('service_category', 'Service category', 'select', true), 'options' => ['Education', 'Financial services', 'Healthcare', 'Technology', 'Recruitment', 'Retail', 'Professional services', 'Other']],
                self::field('directors', 'Names of directors / partners / trustees', 'textarea', $business),
            ]],
            ['title' => 'Contact person', 'fields' => [
                self::field('contact_name', 'Full name', required: true),
                self::field('contact_job_title', 'Designation / job title', required: true),
                self::field('contact_email', 'Contact email', 'email', true),
                self::field('contact_phone', 'Contact phone number', 'tel', true),
                self::field('website', 'Website address', 'url'),
            ]],
            ['title' => 'Supplemental questions', 'fields' => [
                self::field('requires_license', 'Do you require a licence to conduct your business?', 'boolean', true),
                self::field('services_regulated', 'Are your services regulated by any authority?', 'boolean', true),
                self::field('complies_aml', 'Does your organisation comply with anti-money laundering and anti-corruption laws and regulations?', 'boolean', true),
                self::field('aml_policies', 'Do you have policies and procedures for assessing and preventing money laundering and fraud?', 'boolean', true),
                self::field('staff_aml_sanctions', 'Are sanctions maintained for staff who breach anti-money laundering and anti-corruption requirements?', 'boolean', true),
                self::field('confidentiality_agreements', 'Do employees, agents and contractors have non-disclosure agreements?', 'boolean', true),
                self::field('data_protection_policies', 'Have you established policies to protect personal data and comply with data protection laws?', 'boolean', true),
                self::field('staff_data_sanctions', 'Are sanctions maintained for staff who breach data protection and confidentiality obligations?', 'boolean', true),
                self::field('security_measures', 'Do you adopt physical, technical and administrative measures to reduce personal data breaches?', 'boolean', true),
                self::field('operating_countries', 'Countries where you operate', required: true),
                self::field('international_data_transfers', 'Will you transfer personal data received from us to other countries?', 'boolean', true),
                self::field('secondary_data_use', 'Will you use verification reports or personal data for purposes other than background checks or KYC?', 'boolean', true),
                self::field('alternate_database', 'Do you intend to create an alternate database of personal data or verification reports accessed through our service?', 'boolean', true),
                self::field('regulatory_sanctions', 'Have you been sanctioned by a regulator in the last two years for a data breach or non-compliance with data protection laws?', 'boolean', true),
            ]],
            ['title' => 'Data protection officer', 'fields' => [
                self::field('dpo_name', 'Full name', required: true),
                self::field('dpo_address', 'Address', 'textarea', true),
                self::field('dpo_email', 'Contact email', 'email', true),
                self::field('dpo_phone', 'Contact phone number', 'tel', true),
            ]],
        ];
    }

    public static function documents(bool $business): array
    {
        return [
            ['key' => 'incorporation', 'label' => 'Certificate of incorporation', 'required' => $business],
            ['key' => 'directors', 'label' => 'Particulars of directors / partners', 'required' => $business],
            ['key' => 'shareholders', 'label' => 'Particulars of shareholders', 'required' => false],
            ['key' => 'operating_license', 'label' => 'Operating licence', 'required' => false],
        ];
    }

    private static function field(string $key, string $label, string $type = 'text', bool $required = false): array
    {
        return compact('key', 'label', 'type', 'required');
    }
}
