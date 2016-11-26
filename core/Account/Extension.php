<?php
/* * ***************************************************************
 * Copyright © 2014 ICT Innovations Pakistan All Rights Reserved   *
 * Developed By: Nasir Iqbal                                       *
 * Website : http://www.ictinnovations.com/                        *
 * Mail : nasir@ictinnovations.com                                 *
 * *************************************************************** */

class Extension extends Account
{

  /**
   * @property-read string $type
   * @var string 
   */
  protected $type = 'extension';

  public function save()
  {
    parent::save();

    $oToken = new Token();
    $oToken->add('account', $this->token_get());
    $oToken->add('extension', $this->token_get());

    $oVoice = new Voice();
    $template = $oVoice->config_template($this->type, $this->username);
    $extension = $oToken->render_template($template);
    $oVoice->config_save('extension', $this->username, $extension);
  }

  public function delete()
  {
    $oVoice = new Voice();
    $oVoice->config_delete('extension', $this->username);
    parent::delete();
  }

}