<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/docs')]
class DocsController extends AbstractController
{
    #[Route('', name: 'api_docs', methods: ['GET'])]
    public function swaggerUi(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pafodex API Docs</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@4.20.0/swagger-ui.css" />
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@4.20.0/swagger-ui-bundle.js"></script>
  <script src="https://unpkg.com/swagger-ui-dist@4.20.0/swagger-ui-standalone-preset.js"></script>
  <script>
    window.ui = SwaggerUIBundle({
      url: '/api/docs/openapi.yaml',
      dom_id: '#swagger-ui',
      deepLinking: true,
      presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
      layout: 'BaseLayout',
    });
  </script>
</body>
</html>
HTML;

        return new Response($html);
    }

    #[Route('/openapi.yaml', name: 'api_docs_openapi', methods: ['GET'])]
    public function openApiFile(): Response
    {
        $projectDir = dirname(__DIR__, 3);
        $filePath = $projectDir . '/openapi.yaml';

        if (!is_file($filePath)) {
            return new Response('openapi.yaml not found', Response::HTTP_NOT_FOUND);
        }

        return new Response(file_get_contents($filePath), Response::HTTP_OK, [
            'Content-Type' => 'application/yaml',
        ]);
    }
}
