<?php

namespace Tests;

use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (Schema::hasTable('catalog_nodes')) {
            $this->seed(ServiceCatalogSeeder::class);
        }
    }
}
