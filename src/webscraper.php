<?php
  namespace MarioWunderlich;

  require 'vendor/autoload.php';

  use GuzzleHttp\Client;
  use Symfony\Component\DomCrawler\Crawler;

  class GRBJScraper {

      /**
       * The target_url we will fetch information from
       * @var string
       */
      private $target_url = null;

      /**
       * The guzzle client.
       * @var \GuzzleHttp\Client
       */
      private $client = null;

      function __construct($target_url = false)
      {
          $this->target_url = $target_url ?: 'http://archive-grbj-2.s3-website-us-west-1.amazonaws.com/';
      }

      /**
       * @throws \Exception
       */
      protected function initialize_guzzle()
      {
          if ($this->client)
              return;
          if (!$this->target_url)
              throw new \Exception("Target URL is not defined");
          if (!filter_var($this->target_url, FILTER_VALIDATE_URL))
              throw new \Exception("Target URL is not valid");

          $this->client = new Client([
              'base_uri' => $this->target_url,
              'timeout' => 0,
              'allow_redirects' => false
          ]);
      }

      /**
       * @return \GuzzleHttp\Client
       */
      protected function get_client()
      {
          if (!$this->client)
              $this->initialize_guzzle();
          return $this->client;
      }

      /**
       * @param bool $uri
       * @return string
       */
      public function get_content($uri = false)
      {
          // Create a new request
          $request = $this->get_client()->get($uri ?: '/');

          // Get the request body
          $body = $request->getBody();

          // Return the body's content
          return $body->getContents();
      }

      public function get_article_links($content)
      {
          $crawler = new Crawler($content);
          $results = [];

          $trending_links_filter = $crawler->filter('#section-3 .records li');
          $trending_links = $this->get_links_from_filter($trending_links_filter);

          $article_links_filter = $crawler->filter('.record h2.headline');
          $article_links = $this->get_links_from_filter($article_links_filter);

          return array_unique(array_merge($trending_links, $article_links));
      }

      protected function get_links_from_filter($filter)
      {
          $results = [];
          foreach ($filter as $i => $content) {
              $content_crawler = new Crawler($content);
              $results[] = $content_crawler->filter('a')->attr('href');
          }
          return $results;
      }

      function scrape_main_site($content)
      {
          $crawler = new Crawler($content);
          $filter = $crawler->filter('.box1 .records.secondary .record');
          $results = [];

          foreach ($filter as $i => $content) {
              $content_crawler = new Crawler($content);
              $results[] = [
                  'title' => $content_crawler->filter('h2.headline a')->text(),
                  'subject' => $content_crawler->filter('div.abstract')->text()
              ];
          }

          return $results;
      }

      public function scrape_article($link)
      {
          $content = $this->get_content($link);

          $crawler = new Crawler($content);
          $main_selector = ".box1.article .records .record ";
          $section_two = '#section-2 .recent-articles .article-author-bio .records .record .author-info .author_bio ';

          $selectors = [
              'article.title' => $main_selector . "h1",
              'article.date' => '.meta div.date',

              'author.name' => $main_selector . "div.author a",
              'author.twitter' => [$section_two . "a", 'href'],
              'author.bio' => $section_two,
              'author.url' => [$main_selector . "div.author a", 'href']
          ];

          return $this->parse_selectors($crawler, $selectors);
      }

      protected function parse_selectors($crawler, $selectors)
      {
          $result = [];
          foreach ($selectors as $name => $selector) {
              if (empty($selector))
                  continue;

              list($bucket, $key) = explode('.', $name);
              if (!array_key_exists($bucket, $result))
                  $result[$bucket] = [];

              $attr = false;
              if (is_array($selector)) {
                  list($selector, $attr) = $selector;
              }

              $result[$bucket][$key] = $this->get_selector_text($crawler, $selector, $attr);
          }

          return $result;
      }

      function get_selector_text($crawler, $selector, $attr_name=false)
      {
          $filter = $crawler->filter($selector);
          if (!count($filter))
              return null;
          foreach ($filter as $content) {
              if (!$attr_name)
                  return $content->textContent;
              return $content->getAttribute($attr_name);
          }
      }

      function placeholder($someParam)
      {
          $client = new Client([
              'base_uri' => 'http://archive-grbj-2.s3-website-us-west-1.amazonaws.com/',
              'timeout'  => 5.0,
          ]);

          # Request / or root
          $response = $client->request('GET', '/');
          $body = $response->getBody();

          echo $someParam;
      }
  }

?>
