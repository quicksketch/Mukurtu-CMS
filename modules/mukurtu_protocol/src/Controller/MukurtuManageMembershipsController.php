<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

class MukurtuManageMembershipsController extends ControllerBase {

  /**
   * Check access for managing community/protocol memberships.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param \Drupal\node\NodeInterface $node
   *   The community node in which to create a new protocol.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, NodeInterface $node) {
    if ($node->bundle() == 'community' || $node->bundle() == 'protocol') {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  public function content(NodeInterface $node) {
    $build = [];

    // Show the community memberships and all community's protocol memberships.
    if ($node->bundle() == 'community') {
      $build[] = \Drupal::formBuilder()->getForm('\Drupal\mukurtu_community\Form\ManageCommunityMembershipForm', $node);

      $protocol_manager = \Drupal::service('mukurtu_protocol.protocol_manager');
      $protocols = $protocol_manager->getCommunityProtocols($node);

      foreach ($protocols as $protocol) {
        $build[] = \Drupal::formBuilder()->getForm('\Drupal\mukurtu_protocol\Form\ManageProtocolMembershipForm', $protocol);
      }
    }

    // Show protocol memberships only.
    if ($node->bundle() == 'protocol') {
      $build[] = \Drupal::formBuilder()->getForm('\Drupal\mukurtu_protocol\Form\ManageProtocolMembershipForm', $node);
    }

    return $build;
  }

}
