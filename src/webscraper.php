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
       * Get the content at the specified URI, or the homepage if no URI is given.
       *
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

      /**
       * Gets all the links in the homepage.
       *
       * @param $content
       * @return array
       */
      public function get_article_links($content)
      {
          $crawler = new Crawler($content);

          $trending_links_filter = $crawler->filter('#section-3 .records li');
          $trending_links = $this->get_links_from_filter($trending_links_filter);

          $article_links_filter = $crawler->filter('.record h2.headline');
          $article_links = $this->get_links_from_filter($article_links_filter);

          return array_unique(array_merge($trending_links, $article_links));
      }

      /**
       * Given a filter with appropriate selectors, gets & returns the lisk of links.
       * @param $filter
       * @return array
       */
      protected function get_links_from_filter($filter)
      {
          $results = [];
          foreach ($filter as $i => $content) {
              $content_crawler = new Crawler($content);
              $results[] = $content_crawler->filter('a')->attr('href');
          }
          return $results;
      }

      /**
       * @param $link
       * @return array
       */
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
              'author.twitter' => [$section_two . "a", 'href', '/.*twitter.*/'],
              'author.bio' => $section_two,
              'author.url' => [$main_selector . "div.author a", 'href']
          ];

          $article = $this->parse_selectors($crawler, $selectors);
          $article['article']['url'] = $this->target_url . '/' . $link;
          return $article;
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
              $regex = false;
              if (is_array($selector) && count($selector) == 3) {
                  list($selector, $attr, $regex) = $selector;
              }
              elseif (is_array($selector) && count($selector) == 2) {
                  list($selector, $attr) = $selector;
              }

              $value = $this->get_selector_text($crawler, $selector, $regex, $attr);
              $result[$bucket][$key] = $value;
          }

          return $result;
      }

      function get_selector_text($crawler, $selector, $regex=false, $attr_name=false)
      {
          $filter = $crawler->filter($selector);
          if (!count($filter))
              return null;
          foreach ($filter as $content) {
              if (!$attr_name)
                  $value = $content->textContent;
              else
                  $value = $content->getAttribute($attr_name);
              if (!$regex)
                  return $value;
              elseif ($regex && preg_match($regex, $value))
                  return $value;
          }

          return null;
      }

      /**
       * Gets all the articles data.
       *
       * @return array
       */
      function get_data()
      {
          $content = $this->get_content();

          // Get article links form the main website
          $links = $this->get_article_links($content);
          $articles_by_author = [];
          $ignore_list = ['blog', 'directories'];

          foreach ($links as $link) {
              // Skip blog entries for now
              if (preg_match('/.*' . join('|', $ignore_list) . '.*/', $link)) {
                  continue;
              }

              $article = $this->scrape_article($link);
              $author = $article['author']['name'];
              if (empty($author))
                  continue;

              if (!array_key_exists($author, $articles_by_author))
                  $articles_by_author[$author] = [];
              $articles_by_author[$author][] = $article;
          }

          return $articles_by_author;
      }

      function get_author_links($author_url)
      {

      }

      function placeholder($someParam)
      {
      }
  }

?>
