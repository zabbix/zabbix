sbom.json:
	composer CycloneDX:make-sbom --omit=dev --no-interaction \
		--spec-version=1.4 \
		--output-reproducible --output-format=JSON --output-file="$@"

sbom.xml:
	composer CycloneDX:make-sbom --omit=dev --no-interaction \
		--spec-version=1.4 \
		--output-reproducible --output-format=XML --output-file="$@"

sbom: sbom.json

.PHONY: sbom
