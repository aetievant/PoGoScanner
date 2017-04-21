<?php

//SELECT sp.pokemon_id, p.french_name, sp.expiration_date, CONCAT(sp.latitude,',',sp.longitude) AS gps, (sp.iv_attack + sp.iv_defense + sp.iv_stamina) / .45 AS IV, sp.iv_attack, sp.iv_defense, sp.iv_stamina, z.name
//FROM spawn_point sp
//NATURAL JOIN pokemon p
//NATURAL JOIN zone z
//ORDER BY expiration_date

require(dirname(__FILE__).'/config/config.inc.php');

$sql = 'SELECT sp.pokemon_id, p.french_name, sp.expiration_date, CONCAT(sp.latitude,\',\',sp.longitude) AS gps, z.name
FROM spawn_point sp
NATURAL JOIN pokemon p
NATURAL JOIN zone z
ORDER BY expiration_date';

$res = Db::getInstance()->executeS($sql);

//var_dump($res);
?>
<html>
    <body>
        <?php if ($res): ?>
        <table border="1">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Expiration</th>
                    <th>GPS</th>
                    <th>Zone name</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($res as $row): ?>
                <tr>
                    <?php foreach ($row as $col) echo '<td>'.$col.'</td>'; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </body>
</html>