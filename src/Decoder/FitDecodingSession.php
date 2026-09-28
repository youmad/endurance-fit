<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Profile\FitProfileRegistry;
use Youmad\Endurance\Fit\Profile\FitTypeRegistry;

/**
 * One stream's pipeline and shared state. Never share across independent streams.
 * Specialized projections must use these components to share accumulation and
 * developer profiles with the unified message processor.
 */
final readonly class FitDecodingSession
{
    public FitDataMessageDecoder $decoder;
    public FitProfileNormalizer $profiles;
    public FitComponentExtractor $components;
    public FitComponentValueResolver $componentValues;
    public FitDeveloperFieldResolver $developerFields;
    public FitDataMessageProcessor $messages;

    public function __construct(FitProfileRegistry $profiles, FitTypeRegistry $types)
    {
        $this->decoder = new FitDataMessageDecoder();
        $this->profiles = new FitProfileNormalizer($profiles);
        $this->components = new FitComponentExtractor();
        $this->componentValues = new FitComponentValueResolver();
        $this->developerFields = new FitDeveloperFieldResolver(
            profiles: $profiles,
        );
        $this->messages = new FitDataMessageProcessor(
            decoder: $this->decoder,
            profiles: $this->profiles,
            physicalTypes: new FitTypeValueResolver($types),
            components: $this->components,
            componentValues: $this->componentValues,
            componentTypes: new FitComponentTypeValueResolver($types),
            assembler: new FitDataMessageAssembler(),
            developerFields: $this->developerFields,
        );
    }
}
