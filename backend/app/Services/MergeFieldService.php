<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Project;

class MergeFieldService
{
    /**
     * Get all available merge fields.
     */
    public function getAvailableFields(): array
    {
        return [
            [
                'key' => 'client.first_name',
                'label' => 'Client First Name',
                'type' => 'text',
                'category' => 'client'
            ],
            [
                'key' => 'client.last_name',
                'label' => 'Client Last Name',
                'type' => 'text',
                'category' => 'client'
            ],
            [
                'key' => 'client.full_name',
                'label' => 'Client Full Name',
                'type' => 'text',
                'category' => 'client'
            ],
            [
                'key' => 'client.email',
                'label' => 'Client Email',
                'type' => 'text',
                'category' => 'client'
            ],
            [
                'key' => 'client.phone',
                'label' => 'Client Phone',
                'type' => 'text',
                'category' => 'client'
            ],
            [
                'key' => 'client.organization',
                'label' => 'Client Organization',
                'type' => 'text',
                'category' => 'client'
            ],
            [
                'key' => 'project.name',
                'label' => 'Project Name',
                'type' => 'text',
                'category' => 'project'
            ],
            [
                'key' => 'project.description',
                'label' => 'Project Description',
                'type' => 'text',
                'category' => 'project'
            ],
            [
                'key' => 'project.start_date',
                'label' => 'Project Start Date',
                'type' => 'date',
                'category' => 'project'
            ],
            [
                'key' => 'project.due_date',
                'label' => 'Project Due Date',
                'type' => 'date',
                'category' => 'project'
            ],
            [
                'key' => 'project.budget',
                'label' => 'Project Budget',
                'type' => 'currency',
                'category' => 'project'
            ],
            [
                'key' => 'company.name',
                'label' => 'Company Name',
                'type' => 'text',
                'category' => 'company'
            ],
            [
                'key' => 'today',
                'label' => 'Today\'s Date',
                'type' => 'date',
                'category' => 'system'
            ],
            [
                'key' => 'contract.created_date',
                'label' => 'Contract Created Date',
                'type' => 'date',
                'category' => 'contract'
            ],
        ];
    }

    /**
     * Extract merge field values from contact, project, and company.
     */
    public function extractValues(?Contact $contact, ?Project $project, Company $company): array
    {
        $values = [];

        // Client/Contact fields
        if ($contact) {
            $names = explode(' ', $contact->full_name, 2);
            $values['client.first_name'] = $names[0] ?? '';
            $values['client.last_name'] = $names[1] ?? '';
            $values['client.full_name'] = $contact->full_name;
            $values['client.email'] = $contact->email ?? '';
            $values['client.phone'] = $contact->phone ?? '';
            $values['client.organization'] = $contact->organization ?? '';
        }

        // Project fields
        if ($project) {
            $values['project.name'] = $project->name;
            $values['project.description'] = $project->description ?? '';
            $values['project.start_date'] = $project->start_date ? $project->start_date->format('F j, Y') : '';
            $values['project.due_date'] = $project->due_date ? $project->due_date->format('F j, Y') : '';
            $values['project.budget'] = $project->budget ? '$' . number_format($project->budget, 2) : '';
        }

        // Company fields
        $values['company.name'] = $company->name;

        // System fields
        $values['today'] = now()->format('F j, Y');
        $values['contract.created_date'] = now()->format('F j, Y');

        return $values;
    }

    /**
     * Replace merge fields in content with actual values.
     */
    public function replaceMergeFields(string $content, array $values): string
    {
        foreach ($values as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Replace merge fields in template sections.
     */
    public function replaceSections(array $sections, array $values): array
    {
        $replaced = [];

        foreach ($sections as $section) {
            $sectionCopy = $section;
            
            // Replace in section content
            if (isset($sectionCopy['content'])) {
                if (is_string($sectionCopy['content'])) {
                    $sectionCopy['content'] = $this->replaceMergeFields($sectionCopy['content'], $values);
                } elseif (is_array($sectionCopy['content'])) {
                    $sectionCopy['content'] = $this->replaceArrayFields($sectionCopy['content'], $values);
                }
            }
            
            $replaced[] = $sectionCopy;
        }

        return $replaced;
    }

    /**
     * Recursively replace merge fields in arrays.
     */
    private function replaceArrayFields(array $data, array $values): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->replaceMergeFields($value, $values);
            } elseif (is_array($value)) {
                $data[$key] = $this->replaceArrayFields($value, $values);
            }
        }

        return $data;
    }

    /**
     * Validate merge field syntax.
     */
    public function validateSyntax(string $content): array
    {
        $errors = [];
        $availableKeys = array_column($this->getAvailableFields(), 'key');
        
        // Find all merge fields in content
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        
        foreach ($matches[1] as $field) {
            $field = trim($field);
            if (!in_array($field, $availableKeys)) {
                $errors[] = "Unknown merge field: {{$field}}";
            }
        }
        
        return $errors;
    }
}

