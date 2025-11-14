<?php
// public/edit_match.php
require_once __DIR__ . '/../src/db.php';

function toUpper($s) {
    if ($s === null) return null;
    return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
}

$match_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$match_id) {
    header("Location: index.php");
    exit;
}

// Obtener categorías existentes
$cats_res = $mysqli->query("SELECT id, name FROM categories ORDER BY name");
$categories = $cats_res ? $cats_res->fetch_all(MYSQLI_ASSOC) : [];

// Cargar partido actual
$stmt = $mysqli->prepare("
    SELECT m.*, t1.name AS home_name, t2.name AS away_name, c.name AS category_name
    FROM matches m
    JOIN teams t1 ON m.home_team_id = t1.id
    JOIN teams t2 ON m.away_team_id = t2.id
    LEFT JOIN categories c ON m.category_id = c.id
    WHERE m.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $match_id);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();
if (!$match) {
    echo "Partido no encontrado.";
    exit;
}

// Manejar POST: actualizar partido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $match_date = $_POST['match_date'] ?? '';
    $home = trim($_POST['home_team'] ?? '');
    $away = trim($_POST['away_team'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $selected_cat = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $new_category = trim($_POST['new_category'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Normalizar a mayúsculas equipos, categoría nueva y ubicación
    $home_up = toUpper($home);
    $away_up = toUpper($away);
    $location_up = toUpper($location);
    $new_cat_up = $new_category !== '' ? toUpper($new_category) : '';

    // Validación: evitar mismo equipo tras normalizar
    if ($home_up !== '' && $away_up !== '' && $home_up === $away_up) {
        $error = "El equipo local y el equipo visitante no pueden ser el mismo (tras normalizar mayúsculas).";
    }

    if (empty($error) && $match_date && $home && $away) {
        $mysqli->begin_transaction();

        try {
            // Obtener o crear home team (buscando por UPPER(name))
            $stmt = $mysqli->prepare("SELECT id FROM teams WHERE UPPER(name) = ? LIMIT 1");
            $stmt->bind_param('s', $home_up);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            if ($row) {
                $home_id = (int)$row['id'];
            } else {
                $stmt = $mysqli->prepare("INSERT INTO teams (name) VALUES (?)");
                $stmt->bind_param('s', $home_up);
                $stmt->execute();
                $home_id = $stmt->insert_id;
            }

            // Obtener o crear away team
            $stmt = $mysqli->prepare("SELECT id FROM teams WHERE UPPER(name) = ? LIMIT 1");
            $stmt->bind_param('s', $away_up);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            if ($row) {
                $away_id = (int)$row['id'];
            } else {
                $stmt = $mysqli->prepare("INSERT INTO teams (name) VALUES (?)");
                $stmt->bind_param('s', $away_up);
                $stmt->execute();
                $away_id = $stmt->insert_id;
            }

            // Categoría: crear si se indica nueva (guardada en MAYÚSCULAS)
            $category_id = null;
            if ($new_cat_up !== '') {
                $stmt = $mysqli->prepare("SELECT id FROM categories WHERE UPPER(name) = ? LIMIT 1");
                $stmt->bind_param('s', $new_cat_up);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                if ($row) {
                    $category_id = (int)$row['id'];
                } else {
                    $stmt = $mysqli->prepare("INSERT INTO categories (name) VALUES (?)");
                    $stmt->bind_param('s', $new_cat_up);
                    $stmt->execute();
                    $category_id = $stmt->insert_id;
                }
            } elseif (!empty($selected_cat) && $selected_cat !== 'none') {
                $category_id = (int)$selected_cat;
            }

            // Actualizar partido (guardando location en MAYÚSCULAS)
            $stmt = $mysqli->prepare("UPDATE matches SET match_date = ?, home_team_id = ?, away_team_id = ?, category_id = ?, location = ?, notes = ? WHERE id = ?");
            $stmt->bind_param('siiissi', $match_date, $home_id, $away_id, $category_id, $location_up, $notes, $match_id);
            $stmt->execute();

            $mysqli->commit();

            header("Location: view_match.php?id=" . $match_id);
            exit;
        } catch (Exception $e) {
            $mysqli->rollback();
            $error = "Error al guardar el partido: " . $e->getMessage();
        }
    } else {
        if (empty($error) && (! $match_date || ! $home || ! $away)) {
            $error = "Rellena fecha, equipo local y visitante.";
        }
    }
}

// Preparar valores para el formulario (formatea datetime-local)
$dt = '';
if (!empty($match['match_date'])) {
    $dtObj = new DateTime($match['match_date']);
    $dt = $dtObj->format('Y-m-d\TH:i');
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar partido</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>.new-cat-input{display:none;}</style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
      <a class="navbar-brand" href="index.php">Scouting</a>
    </div>
  </nav>

  <main class="container my-4">
    <h1 class="h5">Editar partido</h1>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>

    <form method="post" class="needs-validation" novalidate>
      <div class="mb-3">
        <label class="form-label">Fecha y hora</label>
        <input type="datetime-local" name="match_date" class="form-control" value="<?=htmlspecialchars($dt)?>" required>
      </div>

      <div class="row g-2">
        <div class="col-12 col-md-6">
          <label class="form-label">Equipo local (nombre)</label>
          <input type="text" name="home_team" class="form-control" required value="<?=htmlspecialchars($match['home_name'])?>">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Equipo visitante (nombre)</label>
          <input type="text" name="away_team" class="form-control" required value="<?=htmlspecialchars($match['away_name'])?>">
        </div>
      </div>

      <div class="row g-2 mt-2">
        <div class="col-12 col-md-6">
          <label class="form-label">Categoría</label>
          <select name="category_id" id="categorySelect" class="form-select">
            <option value="none">-- Sin categoría --</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($c['id'] == $match['category_id']) ? 'selected' : '' ?>><?= htmlspecialchars(toUpper($c['name'])) ?></option>
            <?php endforeach; ?>
            <option value="new">+ Añadir nueva categoría...</option>
          </select>
        </div>
        <div class="col-12 col-md-6 new-cat-input" id="newCategoryWrap">
          <label class="form-label">Nueva categoría</label>
          <input type="text" name="new_category" id="newCategory" class="form-control" placeholder="Introduce el nombre de la categoría">
        </div>
      </div>

      <div class="mb-3 mt-3">
        <label class="form-label">Ubicación</label>
        <input type="text" name="location" class="form-control" value="<?=htmlspecialchars($match['location'])?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Nota general del partido (opcional)</label>
        <textarea name="notes" class="form-control" rows="3"><?=htmlspecialchars($match['notes'])?></textarea>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-primary">Guardar cambios</button>
        <a class="btn btn-secondary" href="view_match.php?id=<?= $match_id ?>">Cancelar</a>
      </div>
    </form>
  </main>

  <script>
    // Mostrar input de nueva categoría si se elige la opción "new"
    const catSelect = document.getElementById('categorySelect');
    const newCatWrap = document.getElementById('newCategoryWrap');
    catSelect && catSelect.addEventListener('change', function(){
      if (this.value === 'new') {
        newCatWrap.style.display = 'block';
        document.getElementById('newCategory').focus();
      } else {
        newCatWrap.style.display = 'none';
        document.getElementById('newCategory').value = '';
      }
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>