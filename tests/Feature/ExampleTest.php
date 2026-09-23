<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_page_redirects_to_employee_directory(): void
    {
        $response = $this->get('/');

        $response->assertRedirectToRoute('employees.index');
    }
}
