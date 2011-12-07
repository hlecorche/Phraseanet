<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2010 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * @package
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
class metadata_description_IFD0_Predictor extends metadata_Abstract implements metadata_Interface
{
  const SOURCE = '/rdf:RDF/rdf:Description/IFD0:Predictor';
  const NAME_SPACE = 'IFD0';
  const TAGNAME = 'Predictor';
  const MAX_LENGTH = 0;
  const TYPE = self::TYPE_INT16U;
  const MANDATORY = false;
  const MULTI = false;
  const READONLY = false;

  public static function available_values()
  {
    return array(
        '1' => 'None'
        , '2' => 'Horizontal differencing'
    );
  }

}