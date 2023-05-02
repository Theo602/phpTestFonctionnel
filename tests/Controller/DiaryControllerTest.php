<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DiaryControllerTest extends WebTestCase
{

    private KernelBrowser|null $client = null;
    private $userRepository = null;
    private $user = null;
    private $urlGenerator = null;

    public function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->followRedirects();
        $this->userRepository = $this->client->getContainer()->get('doctrine.orm.entity_manager')->getRepository(User::class);
        $this->user = $this->userRepository->findOneByEmail('email');
        $this->urlGenerator = $this->client->getContainer()->get('router.default');

        //Authentification sur Symfony pour le test avec le user récupéré en base
        $this->client->loginUser($this->user);
    }

    public function testHomepageIsUp()
    {
        $this->client->request(Request::METHOD_GET, $this->urlGenerator->generate('homepage'));
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $crawler = $this->client->request(Request::METHOD_GET, $this->urlGenerator->generate('homepage'));
        $this->assertSame(1, $crawler->filter('html:contains("Bienvenue sur FoodDiary!")')->count());
        $this->assertSame(1, $crawler->filter('h1')->count());
    }

    public function testAddRecord()
    {
        $crawler = $this->client->request(Request::METHOD_GET, $this->urlGenerator->generate('add-new-record'));

        $form = $crawler->selectButton('Enregistrer')->form();
        $form['food[entitled]'] = 'Plat de pates';
        $form['food[calories]'] = "600";
        $this->client->submit($form);

        echo $this->client->getResponse()->getContent();
        $this->assertSelectorTextContains('div.alert.alert-success', 'Une nouvelle entrée dans votre journal a bien été ajoutée');
    }

    public function testAddRecordConstraint()
    {
        $crawler = $this->client->request(Request::METHOD_GET, $this->urlGenerator->generate('add-new-record'));

        $form = $crawler->selectButton('Enregistrer')->form();
        $this->client->submit($form);

        echo $this->client->getResponse()->getContent();

        $this->assertEmpty($form['food[entitled]'] = '', "Veuillez indiquer un intitulé pour ce qui a été consommé");
        $this->assertEmpty($form['food[calories]'] = '', "Veuillez indiquer un nombre de calories");
    }

    public function testList()
    {
        $crawler = $this->client->request(Request::METHOD_GET, $this->urlGenerator->generate('diary'));

        $link = $crawler->selectLink('Voir tous les rapports')->link();
        $crawler = $this->client->click($link);

        $info = $crawler->filter('h1')->text();

        // On retire les retours à la ligne pour faciliter la vérification
        $info = $string = trim(preg_replace('/\s\s+/', ' ', $info));
        $this->assertSame("Tous les rapports Tout ce qui a été mangé !", $info);
    }
}
