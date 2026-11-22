<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Modules\MediaLibrary;

use DOMDocument;
use DOMElement;

/** Static SVG image profile. Rebuilds a fresh document from allowlisted SVG nodes. */
final class SvgSanitizer
{
    private const NS = 'http://www.w3.org/2000/svg';
    private const ID = '[A-Za-z_][A-Za-z0-9_.:-]*';
    private const ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'path', 'rect', 'circle', 'ellipse',
        'line', 'polyline', 'polygon', 'text', 'tspan', 'textPath', 'title', 'desc',
        'linearGradient', 'radialGradient', 'stop', 'clipPath', 'mask', 'pattern',
        'marker', 'filter', 'feBlend', 'feColorMatrix', 'feComponentTransfer',
        'feComposite', 'feConvolveMatrix', 'feDiffuseLighting', 'feDisplacementMap',
        'feDistantLight', 'feDropShadow', 'feFlood', 'feFuncA', 'feFuncB', 'feFuncG',
        'feFuncR', 'feGaussianBlur', 'feMerge', 'feMergeNode', 'feMorphology',
        'feOffset', 'fePointLight', 'feSpecularLighting', 'feSpotLight', 'feTile',
        'feTurbulence', 'style',
    ];
    private const PAINT = ['fill', 'stroke', 'color', 'stop-color', 'flood-color', 'lighting-color'];
    private const REFERENCES = ['clip-path', 'mask', 'filter', 'marker-start', 'marker-mid', 'marker-end'];
    private const PRESENTATION = [
        'fill-opacity', 'fill-rule', 'stroke-opacity', 'stroke-width',
        'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray',
        'stroke-dashoffset', 'opacity', 'stop-opacity', 'flood-opacity', 'clip-rule',
        'display', 'visibility', 'font-size', 'font-weight', 'font-style',
        'font-stretch', 'font-variant', 'text-anchor', 'text-decoration',
        'dominant-baseline', 'alignment-baseline', 'baseline-shift', 'letter-spacing',
        'word-spacing', 'vector-effect', 'paint-order', 'shape-rendering',
        'text-rendering', 'color-interpolation', 'color-interpolation-filters',
    ];
    private const GEOMETRY = [
        'viewBox', 'preserveAspectRatio', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'dx', 'dy',
        'width', 'height', 'cx', 'cy', 'r', 'rx', 'ry', 'd', 'points', 'transform',
        'pathLength', 'textLength', 'lengthAdjust', 'rotate', 'startOffset', 'method',
        'spacing', 'offset', 'gradientUnits', 'gradientTransform', 'spreadMethod',
        'fx', 'fy', 'fr', 'clipPathUnits', 'maskUnits', 'maskContentUnits', 'mask-type',
        'patternUnits', 'patternContentUnits', 'patternTransform', 'markerWidth',
        'markerHeight', 'markerUnits', 'refX', 'refY', 'orient', 'filterUnits',
        'primitiveUnits', 'in', 'in2', 'result', 'mode', 'type', 'values',
        'operator', 'k1', 'k2', 'k3', 'k4', 'stdDeviation', 'scale', 'xChannelSelector',
        'yChannelSelector', 'order', 'kernelMatrix', 'divisor', 'bias', 'targetX',
        'targetY', 'edgeMode', 'preserveAlpha', 'surfaceScale', 'diffuseConstant',
        'specularConstant', 'specularExponent', 'limitingConeAngle', 'azimuth',
        'elevation', 'pointsAtX', 'pointsAtY', 'pointsAtZ', 'z', 'baseFrequency',
        'numOctaves', 'seed', 'stitchTiles', 'tableValues', 'slope', 'intercept',
        'amplitude', 'exponent',
    ];

    /** @return array{xml:string,width:?int,height:?int} */
    public function sanitize(string $source): array
    {
        if ($source === '' || strlen($source) > 5242880 || str_contains($source, "\0") || preg_match('//u', $source) !== 1) {
            throw new \RuntimeException('Nieprawidłowy plik SVG. Wymagany jest dokument XML w UTF-8.');
        }
        // Remove a simple external declaration WITHOUT resolving its identifier.
        // Internal subsets/entities are rejected before invoking the XML parser.
        $source = preg_replace('~<!DOCTYPE\s+svg\s+(?:PUBLIC\s+(?:"[^"]*"|\'[^\']*\')\s+(?:"[^"]*"|\'[^\']*\')|SYSTEM\s+(?:"[^"]*"|\'[^\']*\'))\s*>~i', '', $source) ?? '';
        if (preg_match('~<!\s*(?:DOCTYPE|ENTITY)\b~i', $source)) {
            throw new \RuntimeException('SVG nie może zawierać definicji encji ani wewnętrznego DTD.');
        }
        if (preg_match('~<\?xml\s+[^?]*encoding\s*=\s*[\'"](?!utf-8[\'"])[^\'"]+~i', $source)) {
            throw new \RuntimeException('Nieprawidłowy plik SVG. Wymagany jest dokument XML w UTF-8.');
        }
        $input = new DOMDocument();
        $input->resolveExternals = false;
        $input->substituteEntities = false;
        $input->validateOnParse = false;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $input->loadXML($source, LIBXML_NONET | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $input->documentElement;
        if (!$loaded || !$root instanceof DOMElement || $input->doctype !== null || $root->localName !== 'svg' || !in_array($root->namespaceURI, [null, '', self::NS], true)) {
            throw new \RuntimeException('Plik nie zawiera poprawnego dokumentu SVG.');
        }
        $output = new DOMDocument('1.0', 'UTF-8');
        $nodes = 0;
        $clean = $this->copy($root, $output, 0, $nodes);
        $output->appendChild($clean);
        $xml = $output->saveXML($clean);
        if (!is_string($xml) || strlen($xml) > 5242880 || !$this->hasGraphics($clean)) {
            throw new \RuntimeException('SVG nie zawiera obsługiwanej grafiki statycznej.');
        }
        [$width, $height] = $this->dimensions($clean);
        return ['xml' => $xml, 'width' => $width, 'height' => $height];
    }

    private function copy(DOMElement $source, DOMDocument $output, int $depth, int &$nodes): DOMElement
    {
        if ($depth > 64 || ++$nodes > 10000 || $source->attributes->length > 128) {
            throw new \RuntimeException('SVG jest zbyt złożony. Limit to 10000 elementów i 64 poziomy.');
        }
        $target = $output->createElementNS(self::NS, $source->localName);
        foreach ($source->attributes as $attribute) {
            $name = $attribute->localName;
            if ($attribute->namespaceURI === 'http://www.w3.org/1999/xlink' && $name === 'href') {
                $name = 'href';
            } elseif ($attribute->namespaceURI === 'http://www.w3.org/XML/1998/namespace' && $name === 'space') {
                if (in_array($attribute->value, ['preserve', 'default'], true)) $target->setAttributeNS($attribute->namespaceURI, 'xml:space', $attribute->value);
                continue;
            } elseif ($attribute->namespaceURI !== null && $attribute->namespaceURI !== '') {
                continue;
            }
            $value = $this->attribute($name, $attribute->value);
            if ($value !== null && $value !== '') $target->setAttribute($name, $value);
        }
        if ($source->localName === 'style') {
            // Static selectors + presentation declarations only; never @import/font-face.
            $target->appendChild($output->createTextNode($this->stylesheet($source->textContent)));
            return $target;
        }
        foreach ($source->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if (in_array($child->namespaceURI, [null, '', self::NS], true) && in_array($child->localName, self::ELEMENTS, true)) {
                    $target->appendChild($this->copy($child, $output, $depth + 1, $nodes));
                }
            } elseif (in_array($child->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true) && in_array($source->localName, ['text', 'tspan', 'textPath', 'title', 'desc'], true)) {
                $target->appendChild($output->createTextNode($child->nodeValue ?? ''));
            }
            // Comments, processing instructions, unknown namespaces and their subtrees vanish.
        }
        return $target;
    }

    private function attribute(string $name, string $value): ?string
    {
        $value = trim($value);
        if (strlen($value) > 262144 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) return null;
        if ($name === 'id') return preg_match('~^' . self::ID . '$~D', $value) ? $value : null;
        if ($name === 'class') return preg_match('/^[A-Za-z0-9_\s-]+$/D', $value) ? $value : null;
        if ($name === 'href') return preg_match('~^#' . self::ID . '$~D', $value) ? $value : null;
        if ($name === 'style') return $this->declarations($value);
        if ($name === 'role') return $value === 'img' ? $value : null;
        if (in_array($name, ['aria-label', 'aria-labelledby', 'aria-describedby'], true)) return mb_substr($value, 0, 255);
        if (in_array($name, self::PAINT, true)) {
            if ($this->localReference($value)) return $value;
            return preg_match('/^(?:#[a-f0-9]{3,8}|[a-z]{1,32}|(?:rgb|rgba|hsl|hsla)\([-+0-9.,%\s\/]+\))$/iD', $value) ? $value : null;
        }
        if (in_array($name, self::REFERENCES, true)) return $value === 'none' || $this->localReference($value) ? $value : null;
        if ($name === 'font-family') return preg_match('/^[-a-z0-9_,\s\'\"]+$/iD', $value) ? $value : null;
        if (in_array($name, self::PRESENTATION, true) || in_array($name, self::GEOMETRY, true)) {
            if (preg_match('/\b(?:url|expression|var|attr)\s*\(/i', $value)) return null;
            return preg_match('/^[-a-z0-9\s+.,()%]+$/iD', $value) ? $value : null;
        }
        return null;
    }

    private function localReference(string $value): bool
    {
        return preg_match('~^url\(\s*([\'"]?)#' . self::ID . '\1\s*\)$~iD', $value) === 1;
    }

    private function declarations(string $css): string
    {
        if (preg_match('~[\\\\@{}<>]|/\*~', $css)) return '';
        $result = [];
        foreach (explode(';', $css) as $declaration) {
            $pair = explode(':', $declaration, 2);
            if (count($pair) !== 2) continue;
            $name = strtolower(trim($pair[0]));
            if (!in_array($name, [...self::PAINT, ...self::REFERENCES, ...self::PRESENTATION, 'font-family'], true)) continue;
            $value = $this->attribute($name, trim($pair[1]));
            if ($value !== null && $value !== '') $result[$name] = $name . ':' . $value;
        }
        return implode(';', $result);
    }

    private function stylesheet(string $css): string
    {
        $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? '';
        if (preg_match('~[\\\\@<>]~', $css)) return '';
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
        $result = [];
        foreach ($rules as $rule) {
            $selector = trim($rule[1]);
            if (!preg_match('/^[a-zA-Z0-9_.#,\s>+~-]+$/D', $selector)) continue;
            $declarations = $this->declarations($rule[2]);
            if ($declarations !== '') $result[] = $selector . '{' . $declarations . '}';
        }
        return implode("\n", $result);
    }

    private function hasGraphics(DOMElement $root): bool
    {
        foreach (['path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'use'] as $name) {
            if ($root->getElementsByTagNameNS(self::NS, $name)->length > 0) return true;
        }
        return false;
    }

    /** @return array{0:?int,1:?int} */
    private function dimensions(DOMElement $root): array
    {
        $width = $this->length($root->getAttribute('width'));
        $height = $this->length($root->getAttribute('height'));
        $viewBox = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox')));
        if (count($viewBox) === 4 && count(array_filter($viewBox, 'is_numeric')) === 4) {
            $w = (float) $viewBox[2]; $h = (float) $viewBox[3];
            if (is_finite($w) && is_finite($h) && $w > 0 && $h > 0) {
                if ($width === null && $height === null) { $width = $w; $height = $h; }
                elseif ($width === null) $width = $height * $w / $h;
                elseif ($height === null) $height = $width * $h / $w;
            }
        }
        $bounded = static fn (?float $value): ?int => $value !== null && is_finite($value) && $value > 0 && $value <= 1000000 ? max(1, (int) round($value)) : null;
        return [$bounded($width), $bounded($height)];
    }

    private function length(string $value): ?float
    {
        if (!preg_match('/^([+]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:e[-+]?[0-9]+)?)(px|pt|pc|mm|cm|in)?$/iD', trim($value), $match)) return null;
        $scale = match (strtolower($match[2] ?? '')) { 'pt' => 96 / 72, 'pc' => 16, 'mm' => 96 / 25.4, 'cm' => 96 / 2.54, 'in' => 96, default => 1 };
        return (float) $match[1] * $scale;
    }
}
