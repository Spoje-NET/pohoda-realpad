<?php

declare(strict_types=1);

/**
 * This file is part of the RealpadTakeout package
 *
 * https://github.com/Spoje-NET/PHP-Realpad-Takeout
 *
 * (c) Spoje.Net IT s.r.o. <http://spojenenet.cz/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Validates that Debian packaging artefacts are present and well-formed.
 */
class DebianPackagingTest extends TestCase
{
    private string $debianDir;
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = \dirname(__DIR__);
        $this->debianDir = $this->projectRoot.'/debian';
    }

    public function testAutoloadPhpExists(): void
    {
        $this->assertFileExists(
            $this->debianDir.'/autoload.php',
            'debian/autoload.php must exist for the deb build',
        );
    }

    public function testAutoloadPhpHasInstalledVersionsBlock(): void
    {
        $content = file_get_contents($this->debianDir.'/autoload.php');
        $this->assertStringContainsString(
            'InstalledVersions::reload',
            $content,
            'autoload.php must contain InstalledVersions::reload block',
        );
        $this->assertStringContainsString("'unknown'", $content, 'autoload.php must have placeholder name');
        $this->assertStringContainsString("'0.0.0'", $content, 'autoload.php must have placeholder version');
    }

    public function testAutoloadPhpLoadsSystemAutoloader(): void
    {
        $content = file_get_contents($this->debianDir.'/autoload.php');
        $this->assertStringContainsString(
            '/usr/share/php/mServer/autoload.php',
            $content,
            'autoload.php must load the system mServer autoloader',
        );
    }

    public function testMetainfoXmlExists(): void
    {
        $this->assertFileExists(
            $this->debianDir.'/pohoda-realpad.metainfo.xml',
            'debian/pohoda-realpad.metainfo.xml must exist for dh_installmetainfo',
        );
    }

    public function testMetainfoXmlIsValidXml(): void
    {
        $content = file_get_contents($this->debianDir.'/pohoda-realpad.metainfo.xml');
        $doc = new \DOMDocument();
        $loaded = $doc->loadXML($content);
        $this->assertTrue($loaded, 'metainfo.xml must be valid XML');
    }

    public function testMetainfoXmlComponentType(): void
    {
        $content = file_get_contents($this->debianDir.'/pohoda-realpad.metainfo.xml');
        $this->assertStringContainsString(
            'type="console-application"',
            $content,
            'metainfo.xml component type must be console-application',
        );
    }

    public function testMetainfoXmlHasStockIcon(): void
    {
        $content = file_get_contents($this->debianDir.'/pohoda-realpad.metainfo.xml');
        $this->assertStringContainsString(
            '<icon type="stock">pohoda-realpad</icon>',
            $content,
            'metainfo.xml must declare the pohoda-realpad stock icon',
        );
    }

    public function testMetainfoXmlHasBinaryProvides(): void
    {
        $content = file_get_contents($this->debianDir.'/pohoda-realpad.metainfo.xml');
        $this->assertStringContainsString(
            '<binary>pohoda-bank-to-realpad</binary>',
            $content,
            'metainfo.xml must declare the installed binary',
        );
    }

    public function testManPageExists(): void
    {
        $this->assertFileExists(
            $this->debianDir.'/pohoda-bank-to-realpad.1',
            'Man page debian/pohoda-bank-to-realpad.1 must exist',
        );
    }

    public function testManPageHasRequiredSections(): void
    {
        $content = file_get_contents($this->debianDir.'/pohoda-bank-to-realpad.1');
        $this->assertStringContainsString('.SH NAME', $content);
        $this->assertStringContainsString('.SH SYNOPSIS', $content);
        $this->assertStringContainsString('.SH DESCRIPTION', $content);
        $this->assertStringContainsString('.SH OPTIONS', $content);
    }

    public function testManpagesFileExists(): void
    {
        $this->assertFileExists(
            $this->debianDir.'/pohoda-realpad.manpages',
            'debian/pohoda-realpad.manpages must list man pages for dh_installman',
        );
    }

    public function testRulesHasPkgVars(): void
    {
        $content = file_get_contents($this->debianDir.'/rules');
        $this->assertStringContainsString('PKG_VERSION', $content);
        $this->assertStringContainsString('PKG_SOURCE', $content);
        $this->assertStringContainsString('PKG_TYPE', $content);
    }

    public function testRulesHasNoComposerDebian(): void
    {
        $content = file_get_contents($this->debianDir.'/rules');
        $this->assertStringNotContainsString(
            'composer-debian',
            $content,
            'debian/rules must not reference the obsolete composer-debian approach',
        );
    }

    public function testControlHasNoComposerDebian(): void
    {
        $content = file_get_contents($this->debianDir.'/control');
        $this->assertStringNotContainsString(
            'composer-debian',
            $content,
            'debian/control must not depend on the obsolete composer-debian package',
        );
    }

    public function testControlBuildDependsHasJq(): void
    {
        $content = file_get_contents($this->debianDir.'/control');
        $this->assertStringContainsString('jq', $content);
    }

    public function testSvgIconExists(): void
    {
        $this->assertFileExists(
            $this->projectRoot.'/pohoda-realpad.svg',
            'pohoda-realpad.svg must exist as the AppStream stock icon source',
        );
    }
}
