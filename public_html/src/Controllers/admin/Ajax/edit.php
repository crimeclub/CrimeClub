<?PHP

use src\Business\MemberService;
use src\Business\AdminService;

$member = new MemberService();
$member->redirectIfLoggedOut();

$admin = new AdminService();
$validTables = $admin->getValidTables();
if(isset($_POST) && !empty($_POST['table']) && in_array($_POST['table'], $validTables) && $security->checkToken($_POST['securityToken']))
{
    $table = $_POST['table'];
    $id = (int)$_POST['id'];
    $table = new AdminService($table);
    $check = $table->editRow($id);
    
    $twigVars = array(
        'routing' => $route,
        'securityToken' => $security->getToken(),
        'member' => $_SESSION['cp-logon'],
        'memberObj' => $member,
        'check' => $check,
        'rows' => $check,
        'rowid' => $id,
        'table' => $_POST['table'],
        'uploadDir' => strtolower($_POST['table']),
        'msg' => 'Fout bij ophalen record in de database.'
    );
    
    echo $twig->render('/src/Views/admin/Ajax/edit.twig', $twigVars);
    exit(0);
}
else
    echo $twig->render('/src/Views/admin/Ajax/general.fail.msg.twig', $twigVars = array('msg' => 'Verkeerde gegevens ontvangen.', 'check' => FALSE));
