<?php

declare(strict_types=1);

namespace Siketyan\Loxcan\Scanner\Yarn;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Siketyan\Loxcan\Model\Dependency;
use Siketyan\Loxcan\Model\Package;
use Siketyan\Loxcan\Versioning\SemVer\SemVerVersion;
use Siketyan\Loxcan\Versioning\SemVer\SemVerVersionParser;

class YarnLockParserTest extends TestCase
{
    use ProphecyTrait;

    private const CONTENTS = <<<'EOS'
        # THIS IS AN AUTOGENERATED FILE. DO NOT EDIT THIS FILE DIRECTLY.
        # yarn lockfile v1


        "@types/node@^18":
          version "18.16.16"
          resolved "https://registry.yarnpkg.com/@types/node/-/node-18.16.16.tgz#3b64862856c7874ccf7439e6bab872d245c86d8e"
          integrity sha512-NpaM49IGQQAUlBhHMF82QH80J08os4ZmyF9MkpCzWAGuOHqE4gTEbhzd7L3l5LmWuZ6E0OiC1FweQ4tsiW35+g==

        typescript@^5:
          version "5.0.4"
          resolved "https://registry.yarnpkg.com/typescript/-/typescript-5.0.4.tgz#b217fd20119bd61a94d4011274e0ab369058da3b"
          integrity sha512-cW9T5W9xY37cc+jfEnaUvX91foxtHkza3Nw3wkoF4sSlKn0MONdkdEndig/qPBWXNkmplh3NzayQzCiHM4/hqw==
        EOS;

    private ObjectProphecy $packagePool;
    private ObjectProphecy $versionParser;
    private YarnLockParser $parser;

    protected function setUp(): void
    {
        $this->packagePool = $this->prophesize(YarnPackagePool::class);
        $this->versionParser = $this->prophesize(SemVerVersionParser::class);

        $this->parser = new YarnLockParser(
            $this->packagePool->reveal(),
            $this->versionParser->reveal(),
        );
    }

    public function test(): void
    {
        $cache = $this->prophesize(Package::class)->reveal();
        $fooBarVersion = $this->prophesize(SemVerVersion::class)->reveal();
        $barBazVersion = $this->prophesize(SemVerVersion::class)->reveal();

        $this->packagePool->get('@types/node')->willReturn(null);
        $this->packagePool->get('typescript')->willReturn($cache);
        $this->packagePool->add(Argument::type(Package::class))->shouldBeCalledOnce();

        $this->versionParser->parse('18.16.16')->willReturn($fooBarVersion);
        $this->versionParser->parse('5.0.4')->willReturn($barBazVersion);

        $collection = $this->parser->parse(self::CONTENTS);
        $dependencies = $collection->getDependencies();

        $this->assertCount(2, $dependencies);
        $this->assertContainsOnlyInstancesOf(Dependency::class, $dependencies);

        $this->assertSame('@types/node', $dependencies[0]->getPackage()->getName());
        $this->assertSame($fooBarVersion, $dependencies[0]->getVersion());

        $this->assertSame($cache, $dependencies[1]->getPackage());
        $this->assertSame($barBazVersion, $dependencies[1]->getVersion());
    }
}
