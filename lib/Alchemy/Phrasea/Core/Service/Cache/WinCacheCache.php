<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2012 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Core\Service\Cache;

use Alchemy\Phrasea\Core,
    Alchemy\Phrasea\Core\Service,
    Alchemy\Phrasea\Core\Service\ServiceAbstract,
    Alchemy\Phrasea\Core\Service\ServiceInterface,
    Alchemy\Phrasea\Cache as CacheDriver;

/**
 *
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
class WinCacheCache extends ServiceAbstract
{
    protected $cache;

    public function getDriver()
    {
        if ( ! extension_loaded('wincache')) {
            throw new \Exception('The WinCache cache requires the WinCache extension.');
        }

        if ( ! $this->cache) {
            $this->cache = new CacheDriver\WinCacheCache();

            $this->cache->setNamespace(md5(realpath(__DIR__ . '/../../../../../../')));
        }

        return $this->cache;
    }

    public function getType()
    {
        return 'wincache';
    }
}

