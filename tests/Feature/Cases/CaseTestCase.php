<?php

namespace Tests\Feature\Cases;

use Illuminate\Support\Facades\Storage;
use Tests\Feature\Catalog\CatalogTestCase;

abstract class CaseTestCase extends CatalogTestCase
{
    protected array $legacyCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->legacyCatalog = $this->entry();
        $this->expertGrant($this->legacyCatalog);
        $this->asAccount($this->owner);
        Storage::fake('case-documents');

    }

    protected function input(): array
    {
        return ['title' => 'Store API review', 'problemDescription' => 'I need a software API review for my store.', 'desiredOutcome' => 'Identify reliability improvements.',
            'primaryDomain' => 'technology', 'domains' => ['technology'], 'serviceNeeds' => ['written_consultation'], 'jurisdiction' => 'PS', 'language' => 'en', 'urgency' => 'normal',
            'privacyChoice' => 'private', 'subjectType' => 'self', 'answers' => ['immediateDanger' => false, 'requiresInPerson' => false, 'ambiguousHighRisk' => false], 'lastCompletedStep' => 'review'];
    }

    protected function createCase(?array $input = null): array
    {
        return $this->postJson('/api/user/cases', $input ?? $this->input(), $this->key())->assertCreated()->json('data.item');
    }

    protected function mutation(array $case, string $suffix, array $extra = [], int $status = 200): array
    {
        return $this->postJson('/api/user/cases/'.$case['id'].'/'.$suffix, ['expectedVersion' => $case['version']] + $extra, $this->key())->assertStatus($status)->json('data.item') ?? [];
    }

    protected function current(int $id): array
    {
        return $this->getJson('/api/user/cases/'.$id)->assertOk()->json('data.item');
    }

    protected function prepareV2(array $case): array
    {
        $case = $this->select($case, [$this->scopeInput($this->legacyCatalog)]);

        return $this->action($case, 'confirm', ['confirmed' => true]);
    }

    protected function ready(array $case): array
    {
        $this->action($this->prepareV2($case), 'submit');

        return $this->current($case['id']);
    }
}
