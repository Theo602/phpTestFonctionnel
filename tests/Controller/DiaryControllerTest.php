<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DiaryControllerTest extends WebTestCase
{

    private KernelBrowser|null $client = null;

    public function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testHomepageIsUp()
    {
    }
}
