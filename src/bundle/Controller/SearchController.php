<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Controller;

use Masilia\Bundle\IbexaBlocks\Search\SearchService;
use Masilia\IbexaBlocks\Search\SearchRequest;
use Masilia\IbexaBlocks\Search\SearchSettings;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The search page of a site (the theme's search-results.twig) and the JSON endpoints the search app of a theme calls. What can be
 * searched, sorted and filtered comes from the settings: a request that asks for more gets what the site allows.
 *
 * The route names are the ones the themes already use (`path('search_page')`).
 */
final readonly class SearchController
{
    public function __construct(
        private SearchService $search,
        private SearchSettings $settings,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/search', name: 'search_page', methods: ['GET'])]
    public function page(Request $request): Response
    {
        $type = (string) $request->query->get('type', 'all');
        $types = $type !== 'all' && in_array($type, $this->settings->pageContentTypes, true) ? [$type] : $this->settings->pageContentTypes;
        $page = max(1, (int) $request->query->get('page', 1));
        $term = (string) $request->query->get('q', '');

        try {
            $result = $this->search->search(new SearchRequest($types, $term, page: $page, limit: $this->settings->pageItems, sort: 'published_desc'));
        } catch (\Throwable $e) {
            $this->logger->warning('Search failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            $result = ['posts' => [], 'total' => 0, 'total_pages' => 1];
        }

        return new Response($this->twig->render('@ibexadesign/search-results.twig', [
            'query' => $term,
            'results' => $result['posts'],
            'total' => $result['total'],
            'page' => $page,
            'totalPages' => $result['total_pages'],
            'contentType' => $type,
            'search_config' => [
                'apiEndpoint' => $this->urls->generate('api_posts_search'),
                'contentTypes' => $this->settings->pageContentTypes,
                'itemsPerPage' => $this->settings->pageItems,
                'totalPosts' => $result['total'],
                'enableSearch' => true,
                'enableTypeFilter' => true,
                'enableSortFilter' => true,
                'popularSearches' => $this->settings->popularSearches,
            ],
        ]));
    }

    #[Route('/api/posts/search', name: 'api_posts_search', methods: ['POST'])]
    public function posts(Request $request): JsonResponse
    {
        return $this->json($request, fn (array $data): array => $this->search->search(SearchRequest::fromArray($data, $this->settings)));
    }

    #[Route('/api/posts/facets', name: 'api_posts_facets', methods: ['POST'])]
    public function facets(Request $request): JsonResponse
    {
        return $this->json($request, function (array $data): array {
            $parsed = SearchRequest::fromArray($data, $this->settings);

            return $this->search->facets($parsed->contentTypes, $parsed->parentSubtree);
        });
    }

    #[Route('/api/posts/suggestions', name: 'api_posts_suggestions', methods: ['GET'])]
    public function suggestions(Request $request): JsonResponse
    {
        $term = trim((string) $request->query->get('q', ''));
        if (mb_strlen($term) < 2) {
            return new JsonResponse(['success' => true, 'data' => []]);
        }

        return $this->json($request, function () use ($request, $term): array {
            $result = $this->search->search(SearchRequest::fromArray([
                'search' => $term,
                'content_types' => explode(',', (string) $request->query->get('content_types', '')),
                'limit' => 5,
            ], $this->settings));

            return array_map(static fn (array $post): array => ['title' => $post['title'] ?? '', 'url' => $post['url'] ?? ($post['link']['uri'] ?? '')], $result['posts']);
        }, decode: false);
    }

    /**
     * @param callable(array<string, mixed>): mixed $handle
     */
    private function json(Request $request, callable $handle, bool $decode = true): JsonResponse
    {
        try {
            $data = $decode ? json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR) : [];
            if (!is_array($data)) {
                return new JsonResponse(['success' => false, 'error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
            }

            return new JsonResponse(['success' => true, 'data' => $handle($data)]);
        } catch (\JsonException) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            // What went wrong is logged, not told to the visitor.
            $this->logger->error('Search request failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return new JsonResponse(['success' => false, 'error' => 'The search failed. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
