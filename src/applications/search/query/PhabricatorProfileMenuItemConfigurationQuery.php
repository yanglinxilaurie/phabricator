<?php

final class PhabricatorProfileMenuItemConfigurationQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $profilePHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withProfilePHIDs(array $phids) {
    $this->profilePHIDs = $phids;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorProfileMenuItemConfiguration();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->profilePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'profilePHID IN (%Ls)',
        $this->profilePHIDs);
    }

    return $where;
  }

  protected function willFilterPage(array $page) {
    $items = PhabricatorProfileMenuItem::getAllMenuItems();
    foreach ($page as $key => $item) {
      $item_type = idx($items, $item->getMenuItemKey());
      if (!$item_type) {
        $this->didRejectResult($item);
        unset($page[$key]);
        continue;
      }
      $item->attachMenuItem($item_type);
    }

    if (!$page) {
      return array();
    }

    $profile_phids = mpull($page, 'getProfilePHID');

    $profiles = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($profile_phids)
      ->execute();
    $profiles = mpull($profiles, null, 'getPHID');

    foreach ($page as $key => $item) {
      $profile = idx($profiles, $item->getProfilePHID());
      if (!$profile) {
        $this->didRejectResult($item);
        unset($page[$key]);
        continue;
      }
      $item->attachProfileObject($profile);
    }

    return $page;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

}
