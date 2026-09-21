<?php

use PHPUnit\Framework\TestCase;

final class CapabilitiesTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__dbem_roles'] = array('administrator' => new WP_Role());
        $GLOBALS['__dbem_users'] = array();
        $GLOBALS['__dbem_user_meta'] = array();
        $GLOBALS['__dbem_current_caps'] = array();
        unset($GLOBALS['__dbem_options']['dbem_caps_version']);
        $_POST = array();
    }

    public function testEventCapabilitiesDoNotIncludeGlobalSettings(): void {
        $capabilities = DBEM_CPT::get_event_capabilities();

        $this->assertContains(DBEM_CPT::EVENT_MANAGER_CAP, $capabilities);
        $this->assertNotContains('manage_options', $capabilities);
        $this->assertNotContains('upload_files', $capabilities);
    }

    public function testAdministratorReceivesCapabilitiesOnlyOnce(): void {
        $administrator = get_role('administrator');

        DBEM_CPT::ensure_event_capabilities();
        $calls = $administrator->add_cap_calls;
        DBEM_CPT::ensure_event_capabilities();

        foreach (DBEM_CPT::get_event_capabilities() as $capability) {
            $this->assertArrayHasKey($capability, $administrator->capabilities);
        }
        $this->assertSame(DBEM_CPT::CAPS_VERSION, get_option('dbem_caps_version'));
        $this->assertSame($calls, $administrator->add_cap_calls);
    }

    public function testCanManageEventsFollowsDedicatedCapability(): void {
        $GLOBALS['__dbem_current_caps'] = array('manage_options');
        $this->assertFalse(DBEM_Admin::can_manage_events());

        $GLOBALS['__dbem_current_caps'] = array(DBEM_CPT::EVENT_MANAGER_CAP);
        $this->assertTrue(DBEM_Admin::can_manage_events());
    }

    public function testNonAdministratorCannotGrantCapabilities(): void {
        $GLOBALS['__dbem_current_caps'] = array('edit_user', DBEM_CPT::EVENT_MANAGER_CAP);
        $_POST['dbem_manage_events'] = '1';

        DBEM_Admin::save_event_manager_field(5);

        $this->assertFalse((new WP_User(5))->has_cap(DBEM_CPT::EVENT_MANAGER_CAP));
    }

    public function testEnablingAndDisablingDelegateRestoresUploadFiles(): void {
        $GLOBALS['__dbem_current_caps'] = array('edit_user', 'manage_options');

        $_POST['dbem_manage_events'] = '1';
        DBEM_Admin::save_event_manager_field(5);
        $user = new WP_User(5);
        foreach (DBEM_CPT::get_event_capabilities() as $capability) {
            $this->assertTrue($user->has_cap($capability));
        }
        $this->assertTrue($user->has_cap('upload_files'));

        $_POST = array();
        DBEM_Admin::save_event_manager_field(5);
        $user = new WP_User(5);
        $this->assertFalse($user->has_cap(DBEM_CPT::EVENT_MANAGER_CAP));
        $this->assertFalse($user->has_cap('upload_files'));
        $this->assertSame('', get_user_meta(5, 'dbem_granted_upload_files', true));
    }

    public function testDisablingKeepsUploadFilesGrantedByRole(): void {
        $GLOBALS['__dbem_current_caps'] = array('edit_user', 'manage_options');
        $author = new WP_User(7);
        $author->role_caps['upload_files'] = true;

        $_POST['dbem_manage_events'] = '1';
        DBEM_Admin::save_event_manager_field(7);
        $this->assertSame('', get_user_meta(7, 'dbem_granted_upload_files', true));

        $_POST = array();
        DBEM_Admin::save_event_manager_field(7);
        $this->assertTrue((new WP_User(7))->has_cap('upload_files'));
    }
}
