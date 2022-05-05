<?php
namespace Flowpack\FullPageCache\Cache;

use Neos\Cache\Frontend\StringFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\Exception\InvalidDataTypeException;
use Neos\Flow\Utility\Environment;
use Psr\Log\LoggerInterface;

/**
 * A string frontend that stores cache metadata (tags, lifetime) for entries
 * Copied from MOC.Varnish
 */
class MetadataAwareStringFrontend extends StringFrontend
{
    protected const SEPARATOR = '|';

    /**
     * Store metadata of all loaded cache entries indexed by identifier
     */
    protected array $metadata = [];

    #[Flow\Inject]
    protected Environment $environment;

    #[Flow\Inject]
    protected LoggerInterface $logger;

    /**
     * Set a cache entry and store additional metadata (tags and lifetime)
     *
     * {@inheritdoc}
     */
    public function set(string $entryIdentifier, $string, array $tags = [], int $lifetime = null): void
    {
        $content = $this->insertMetadata($string, $entryIdentifier, $tags, $lifetime);
        parent::set($entryIdentifier, $content, $tags, $lifetime);
    }

    /**
     * {@inheritdoc}
     * @throws InvalidDataTypeException
     */
    public function get(string $entryIdentifier): bool|string
    {
        $content = parent::get($entryIdentifier);
        if ($content !== false) {
            $content = $this->extractMetadata($entryIdentifier, $content);
        }

        return $content;
    }

    /**
     * {@inheritdoc}
     * @throws InvalidDataTypeException
     */
    public function getByTag(string $tag): array
    {
        $entries = parent::getByTag($tag);
        foreach ($entries as $identifier => $content) {
            $entries[$identifier] = $this->extractMetadata($identifier, $content);
        }

        return $entries;
    }

    /**
     * Insert metadata into the content
     *
     * @param string $entryIdentifier The identifier metadata
     * @param array $tags The tags metadata
     * @param integer|null $lifetime The lifetime metadata
     * @return string The content including the serialized metadata
     */
    protected function insertMetadata(string $content, string $entryIdentifier, array $tags, int $lifetime = null): string
    {
        $metadata = [
            'identifier' => $entryIdentifier,
            'tags' => $tags,
            'lifetime' => $lifetime
        ];
        $metadataJson = json_encode($metadata);
        $this->metadata[$entryIdentifier] = $metadata;

        return $metadataJson . self::SEPARATOR . $content;
    }

    /**
     * Extract metadata from the content and store it
     *
     * @param string $entryIdentifier The entry identifier
     * @param string $content The raw content including serialized metadata
     * @return string The content without metadata
     * @throws InvalidDataTypeException
     */
    protected function extractMetadata(string $entryIdentifier, string $content): string
    {
        $separatorIndex = strpos($content, self::SEPARATOR);
        if ($separatorIndex === false) {
            $exception = new InvalidDataTypeException('Could not find cache metadata in entry with identifier ' . $entryIdentifier, 1433155925);
            if ($this->environment->getContext()->isProduction()) {
                $this->logger->error($exception->getMessage());
            } else {
                throw $exception;
            }
        }

        $metadataJson = substr($content, 0, $separatorIndex);
        $metadata = json_decode($metadataJson, true);
        if ($metadata === null) {
            $exception = new InvalidDataTypeException('Invalid cache metadata in entry with identifier ' . $entryIdentifier, 1433155926);
            if ($this->environment->getContext()->isProduction()) {
                $this->logger->error($exception->getMessage());
            } else {
                throw $exception;
            }
        }

        $this->metadata[$entryIdentifier] = $metadata;

        return substr($content, $separatorIndex + 1);
    }

    /**
     * @return array Metadata of all loaded entries (indexed by identifier)
     */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }
}
