<?php

class createViewsTask extends sfBaseTask
{
  protected function configure()
  {

    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
      new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The connection name', 'doctrine'),
      // add your own options here
    ));

    $this->namespace        = 'couch';
    $this->name             = 'refreshViews';
    $this->briefDescription = 'Refreshes the couchdb views';
    $this->detailedDescription = <<<EOF
The [couch:createViews|INFO] task refreshes the couchdb views.
Call it with:

  [php symfony couch:createViews|INFO]
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    sfCouchView::checkDesignDoc();
    $this->logSection('couch', 'Refreshing the views');
  }
}
