<?PHP

require_once __DIR__ . '/.inc.head.php';

require_once __DIR__ . '/.inc.foot.php';

//$twigVars['langs'] = array_merge($twigVars['langs'], $language->statusLangs()); // Extend base langs

echo $twig->render('/src/Views/game/click-mission.twig', $twigVars);
