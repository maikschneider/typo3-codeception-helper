<?php

declare(strict_types=1);

/*
 * This file is part of the Composer package "eliashaeussler/typo3-codeception-helper".
 *
 * Copyright (C) 2023 Elias Häußler <elias@haeussler.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace EliasHaeussler\Typo3CodeceptionHelper\Codeception\Extension;

use Codeception\Configuration;
use Codeception\Events;
use Codeception\Extension;
use EliasHaeussler\Typo3CodeceptionHelper\Exception;
use EliasHaeussler\Typo3CodeceptionHelper\Helper;
use EliasHaeussler\Typo3CodeceptionHelper\Template;
use Symfony\Component\Filesystem;

use function is_string;
use function rtrim;

/**
 * ApplicationEntrypointModifier.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
final class ApplicationEntrypointModifier extends Extension
{
    /**
     * @var array<string, string>
     */
    protected static array $events = [
        Events::SUITE_BEFORE => 'beforeSuite',
    ];

    protected array $config = [
        'web-dir' => null,
        'main-entrypoint' => 'index.php',
        'app-entrypoint' => 'app.php',
    ];

    /**
     * @var non-empty-string
     */
    private string $webDirectory;

    /**
     * @var non-empty-string
     */
    private string $mainEntrypoint;

    /**
     * @var non-empty-string
     */
    private string $appEntrypoint;

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $options
     */
    public function __construct(
        array $config,
        array $options,
        private readonly Template\TemplateRenderer $templateRenderer = new Template\TemplateRenderer(),
        private readonly Filesystem\Filesystem $filesystem = new Filesystem\Filesystem(),
    ) {
        parent::__construct($config, $options);
    }

    /**
     * @throws Exception\ConfigIsEmpty
     * @throws Exception\ConfigIsInvalid
     */
    public function _initialize(): void
    {
        $this->webDirectory = $this->initializeWebDirectory();
        $this->mainEntrypoint = $this->initializeEntrypoint('main-entrypoint');
        $this->appEntrypoint = $this->initializeEntrypoint('app-entrypoint');
    }

    public function beforeSuite(): void
    {
        if ($this->entrypointNeedsUpdate()) {
            $this->createEntrypoint(true);
        }
    }

    /**
     * @return non-empty-string
     */
    public function getWebDirectory(): string
    {
        return $this->webDirectory;
    }

    /**
     * @return non-empty-string
     */
    public function getMainEntrypoint(): string
    {
        return $this->mainEntrypoint;
    }

    /**
     * @return non-empty-string
     */
    public function getAppEntrypoint(): string
    {
        return $this->appEntrypoint;
    }

    private function entrypointNeedsUpdate(): bool
    {
        if (!$this->filesystem->exists($this->appEntrypoint)) {
            return true;
        }

        return sha1_file($this->mainEntrypoint) !== sha1($this->createEntrypoint());
    }

    private function createEntrypoint(bool $dump = false): string
    {
        $templateFile = 'entrypoint.php.tpl';
        $variables = [
            'projectDir' => rtrim(Configuration::projectDir(), DIRECTORY_SEPARATOR),
            'vendorDir' => Helper\PathHelper::getVendorDirectory(),
            'appEntrypoint' => $this->appEntrypoint,
        ];

        if (!$dump) {
            return $this->templateRenderer->render($templateFile, $variables);
        }

        $this->filesystem->rename($this->mainEntrypoint, $this->appEntrypoint, true);

        return $this->templateRenderer->dump($templateFile, $this->mainEntrypoint, $variables);
    }

    /**
     * @return non-empty-string
     *
     * @throws Exception\ConfigIsEmpty
     * @throws Exception\ConfigIsInvalid
     */
    private function initializeWebDirectory(): string
    {
        $webDir = $this->config['web-dir'];

        if (!is_string($webDir)) {
            throw new Exception\ConfigIsInvalid('web-dir');
        }
        if ('' === $webDir) {
            throw new Exception\ConfigIsEmpty('web-dir');
        }

        return Filesystem\Path::join(Configuration::projectDir(), $webDir);
    }

    /**
     * @param non-empty-string $name
     *
     * @return non-empty-string
     *
     * @throws Exception\ConfigIsEmpty
     * @throws Exception\ConfigIsInvalid
     */
    private function initializeEntrypoint(string $name): string
    {
        $entrypoint = $this->config[$name];

        if (!is_string($entrypoint)) {
            throw new Exception\ConfigIsInvalid($name);
        }
        if ('' === $entrypoint) {
            throw new Exception\ConfigIsEmpty($name);
        }

        return Filesystem\Path::join($this->webDirectory, $entrypoint);
    }
}
