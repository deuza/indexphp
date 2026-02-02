<?php
/**
 * Directory Listing avec navigation dans les sous-répertoires
 * Affiche fichiers et dossiers avec possibilité de naviguer
 */

// Configuration
$baseDir = __DIR__;  // Répertoire racine (sécurité)
$hiddenFiles = ['.htaccess', '.', '..', basename(__FILE__)];
$defaultSort = 'name';
$defaultOrder = 'asc';

// Récupération du répertoire demandé
$requestedDir = $_GET['dir'] ?? '';

/**
 * Sécurise et valide un chemin relatif
 */
function securePath(string $base, string $requested): string {
    // Normalise le chemin demandé
    $requested = str_replace(['\\', '../'], ['/', ''], $requested);
    $requested = trim($requested, '/');
    
    // Construit le chemin complet
    $fullPath = $requested ? $base . '/' . $requested : $base;
    $realPath = realpath($fullPath);
    
    // Vérifie qu'on reste dans le répertoire de base
    if ($realPath === false || strpos($realPath, realpath($base)) !== 0) {
        return $base;
    }
    
    return $realPath;
}

$currentDir = securePath($baseDir, $requestedDir);

// Paramètres de tri
$sort = $_GET['sort'] ?? $defaultSort;
$order = $_GET['order'] ?? $defaultOrder;

if (!in_array($sort, ['name', 'size', 'date', 'type'])) $sort = $defaultSort;
if (!in_array($order, ['asc', 'desc'])) $order = $defaultOrder;

/**
 * Formate une taille en octets
 */
function formatSize(int $bytes): string {
    if ($bytes === 0) return '—';
    $units = ['o', 'Ko', 'Mo', 'Go', 'To'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Génère l'URL de tri
 */
function sortUrl(string $column, string $currentSort, string $currentOrder, string $currentPath): string {
    $newOrder = ($column === $currentSort && $currentOrder === 'asc') ? 'desc' : 'asc';
    $params = "sort={$column}&order={$newOrder}";
    if ($currentPath) $params .= "&dir=" . urlencode($currentPath);
    return "?{$params}";
}

/**
 * Indicateur de tri
 */
function sortIndicator(string $column, string $currentSort, string $currentOrder): string {
    if ($column !== $currentSort) return '';
    return $currentOrder === 'asc' ? ' ▲' : ' ▼';
}

/**
 * Calcule le chemin relatif par rapport à la base
 */
function getRelativePath(string $base, string $current): string {
    $base = rtrim(realpath($base), '/');
    $current = rtrim(realpath($current), '/');
    
    if ($current === $base) return '';
    
    return substr($current, strlen($base) + 1);
}

// Lecture du répertoire
$items = [];
if (is_dir($currentDir) && $handle = opendir($currentDir)) {
    while (($file = readdir($handle)) !== false) {
        if (in_array($file, $hiddenFiles)) continue;

        $filepath = $currentDir . '/' . $file;
        $isDir = is_dir($filepath);
        
        $items[] = [
            'name' => $file,
            'path' => $filepath,
            'size' => $isDir ? 0 : filesize($filepath),
            'date' => filemtime($filepath),
            'ext'  => $isDir ? 'dir' : (strtolower(pathinfo($file, PATHINFO_EXTENSION)) ?: 'file'),
            'is_dir' => $isDir
        ];
    }
    closedir($handle);
}

// Tri : dossiers d'abord, puis selon critère choisi
usort($items, function($a, $b) use ($sort, $order) {
    // Les dossiers toujours en premier
    if ($a['is_dir'] && !$b['is_dir']) return -1;
    if (!$a['is_dir'] && $b['is_dir']) return 1;
    
    $cmp = match($sort) {
        'size' => $a['size'] <=> $b['size'],
        'date' => $a['date'] <=> $b['date'],
        'type' => strcasecmp($a['ext'], $b['ext']),
        default => strcasecmp($a['name'], $b['name'])
    };
    return $order === 'desc' ? -$cmp : $cmp;
});

// Statistiques
$totalFiles = count(array_filter($items, fn($i) => !$i['is_dir']));
$totalDirs = count(array_filter($items, fn($i) => $i['is_dir']));
$totalSize = array_sum(array_column($items, 'size'));

// Chemin actuel relatif et parent
$relativePath = getRelativePath($baseDir, $currentDir);
$parentPath = $relativePath ? dirname($relativePath) : null;
if ($parentPath === '.') $parentPath = '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📁 <?= $relativePath ?: 'Fichiers' ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace;
            background: #1a1a2e;
            color: #eee;
            margin: 0;
            padding: 20px;
        }
        h1 { color: #00d4ff; margin-bottom: 5px; word-break: break-all; }
        .stats { color: #888; margin-bottom: 15px; font-size: 0.9em; }
        .breadcrumb {
            margin-bottom: 15px;
            padding: 10px;
            background: #16213e;
            border-radius: 5px;
        }
        .breadcrumb a {
            color: #00d4ff;
            text-decoration: none;
            padding: 5px 10px;
            display: inline-block;
        }
        .breadcrumb a:hover { background: #0f3460; border-radius: 3px; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #16213e;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td { padding: 12px 15px; text-align: left; }
        th {
            background: #0f3460;
            color: #00d4ff;
            cursor: pointer;
            user-select: none;
        }
        th:hover { background: #1a4a7a; }
        tr:nth-child(even) { background: #1a2744; }
        tr:hover { background: #234; }
        a { color: #00d4ff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .size, .date { white-space: nowrap; }
        .ext {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.75em;
            background: #0f3460;
            color: #00d4ff;
            text-transform: uppercase;
            min-width: 40px;
            text-align: center;
        }
        .ext.dir {
            background: #4a7a1a;
            color: #8fff00;
            font-weight: bold;
        }
        .icon { margin-right: 8px; }
        .empty { text-align: center; padding: 40px; color: #666; }
        @media (max-width: 600px) {
            th, td { padding: 8px; font-size: 0.9em; }
            .date { display: none; }
        }
    </style>
</head>
<body>
    <h1>📁 <?= htmlspecialchars($relativePath ?: 'Racine') ?></h1>
    <p class="stats">
        <?= $totalDirs ?> dossier(s) — <?= $totalFiles ?> fichier(s) — <?= formatSize($totalSize) ?> au total
    </p>

    <?php if ($parentPath !== null): ?>
    <div class="breadcrumb">
        <a href="?<?= $parentPath ? 'dir=' . urlencode($parentPath) : '' ?>">⬆️ Remonter</a>
        <?php if ($relativePath): ?>
        <a href="?">🏠 Racine</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <p class="empty">Répertoire vide.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><a href="<?= sortUrl('name', $sort, $order, $relativePath) ?>">Nom<?= sortIndicator('name', $sort, $order) ?></a></th>
                    <th class="size"><a href="<?= sortUrl('size', $sort, $order, $relativePath) ?>">Taille<?= sortIndicator('size', $sort, $order) ?></a></th>
                    <th class="date"><a href="<?= sortUrl('date', $sort, $order, $relativePath) ?>">Date<?= sortIndicator('date', $sort, $order) ?></a></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <span class="ext <?= $item['is_dir'] ? 'dir' : '' ?>">
                            <?= $item['is_dir'] ? '📁' : htmlspecialchars($item['ext']) ?>
                        </span>
                        <?php if ($item['is_dir']): ?>
                            <?php $newPath = $relativePath ? $relativePath . '/' . $item['name'] : $item['name']; ?>
                            <a href="?dir=<?= urlencode($newPath) ?>">
                                <?= htmlspecialchars($item['name']) ?>
                            </a>
                        <?php else: ?>
                            <?php $filePath = $relativePath ? $relativePath . '/' . $item['name'] : $item['name']; ?>
                            <a href="<?= rawurlencode($filePath) ?>" target="_blank">
                                <?= htmlspecialchars($item['name']) ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td class="size"><?= formatSize($item['size']) ?></td>
                    <td class="date"><?= date('d/m/Y H:i', $item['date']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
