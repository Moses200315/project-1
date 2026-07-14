<?php

/**
 * MealKit Database Seeder  v1.0.0
 * ================================
 * Populates the database with:
 *   - 1 Super Admin account
 *   - 2 Customer accounts
 *   - 3 Subscription Plans  (Free / Basic / Premium)
 *   - 8 Recipe Categories
 *   - 6 Full Recipes  (with ingredients & step-by-step procedures)
 *   - 2 Sample Subscriptions + Payments
 *   - Meal Plans, Favourites, Notifications, Download logs
 *
 * PREREQUISITES : Import  database/mealkit.sql  first.
 *
 * HOW TO RUN    : Visit http://localhost/mealkit/database/seed.php
 *                 (Only accessible from localhost for security)
 *
 * !! DELETE THIS FILE BEFORE DEPLOYING TO PRODUCTION !!
 */

declare(strict_types=1);

// ── Security: restrict execution to localhost only ─────────────────────────
$allowedIPs = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowedIPs, true)) {
    http_response_code(403);
    die('403 Forbidden – This seeder can only be run from localhost.');
}

// ── Database credentials (update if your XAMPP config differs) ─────────────
define('SEED_DB_HOST', 'localhost');
define('SEED_DB_NAME', 'mealkit_db');
define('SEED_DB_USER', 'root');
define('SEED_DB_PASS', '');          // XAMPP default: empty password
define('SEED_DB_PORT', '3306');

// ── Output helpers ──────────────────────────────────────────────────────────
ob_start();

/**
 * Render a styled log line to the browser.
 */
function log_line(string $icon, string $msg, string $cls = 'text-secondary'): void
{
    echo "<p class=\"mb-1 {$cls}\"><span class=\"me-2\">{$icon}</span>{$msg}</p>\n";
    ob_flush();
    flush();
}

function log_ok(string $msg):    void { log_line('✅', $msg, 'text-success'); }
function log_info(string $msg):  void { log_line('ℹ️', $msg, 'text-info');    }
function log_skip(string $msg):  void { log_line('⏭️',  $msg, 'text-warning'); }
function log_err(string $msg):   void { log_line('❌', $msg, 'text-danger');  }
function log_head(string $msg):  void
{
    echo "<h6 class=\"mt-3 mb-1 fw-bold text-dark border-bottom pb-1\">{$msg}</h6>\n";
    ob_flush(); flush();
}

// ── HTML header ─────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MealKit – Database Seeder</title>
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
    body { background: #f8f9fa; }
    .card { max-width: 860px; margin: 40px auto; }
    pre  { background:#1e1e1e; color:#d4d4d4; border-radius:6px;
           padding:12px; font-size:.8rem; }
</style>
</head>
<body>
<div class="card shadow-sm">
  <div class="card-header bg-success text-white d-flex align-items-center gap-2">
    <strong>🍽️ MealKit – Database Seeder</strong>
    <span class="ms-auto badge bg-light text-dark">v1.0.0</span>
  </div>
  <div class="card-body">
<?php

// ── PDO connection ──────────────────────────────────────────────────────────
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        SEED_DB_HOST, SEED_DB_PORT, SEED_DB_NAME
    );
    $pdo = new PDO($dsn, SEED_DB_USER, SEED_DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    log_ok('Connected to <strong>' . SEED_DB_NAME . '</strong> on ' . SEED_DB_HOST);
} catch (PDOException $e) {
    log_err('Database connection failed: ' . htmlspecialchars($e->getMessage()));
    echo '</div></div></body></html>';
    exit;
}

// ── Idempotency guard ───────────────────────────────────────────────────────
$alreadySeeded = (int) $pdo->query("SELECT COUNT(*) FROM `admins`")->fetchColumn() > 0;
if ($alreadySeeded) {
    log_skip('Database is already seeded. Drop & re-import mealkit.sql to re-seed.');
    echo '<div class="alert alert-warning mt-3">Seeder aborted – data already present.</div>';
    echo '</div></div></body></html>';
    exit;
}

// ── Helper: generic insert ──────────────────────────────────────────────────
/**
 * Insert a row and return its new ID.
 *
 * @param PDO    $pdo
 * @param string $table
 * @param array  $data  column => value pairs
 * @return int   Last insert ID
 */
function db_insert(PDO $pdo, string $table, array $data): int
{
    $cols        = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($data)));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$cols}) VALUES ({$placeholders})");
    $stmt->execute(array_values($data));
    return (int) $pdo->lastInsertId();
}

// ── Disable FK checks for seeding ──────────────────────────────────────────
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

/* ============================================================
   SEED 1 — ADMINS
   ============================================================ */
log_head('👤 Seeding Admins');

$adminId = db_insert($pdo, 'admins', [
    'first_name' => 'Super',
    'last_name'  => 'Admin',
    'email'      => 'admin@mealkit.com',
    'password'   => password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]),
    'avatar'     => 'default.png',
    'role'       => 'super_admin',
    'status'     => 'active',
]);
log_ok("Admin created  →  ID: {$adminId}  |  admin@mealkit.com  /  Admin@123");

/* ============================================================
   SEED 2 — USERS (Customers)
   ============================================================ */
log_head('👥 Seeding Customers');

$user1Id = db_insert($pdo, 'users', [
    'first_name'        => 'Samson',
    'last_name'         => 'Ligima',
    'email'             => 'samson@mealkit.com',
    'password'          => password_hash('User@1234', PASSWORD_BCRYPT, ['cost' => 12]),
    'phone'             => '+255700123456',
    'avatar'            => 'default.png',
    'bio'               => 'Food lover from Dar es Salaam who enjoys trying new recipes every weekend.',
    'status'            => 'active',
    'email_verified_at' => date('Y-m-d H:i:s'),
]);
log_ok("Customer 1  →  ID: {$user1Id}  |  samson@mealkit.com  /  User@1234");

$user2Id = db_insert($pdo, 'users', [
    'first_name'        => 'Samson',
    'last_name'         => 'Ligima',
    'email'             => 'samson2@mealkit.com',
    'password'          => password_hash('User@1234', PASSWORD_BCRYPT, ['cost' => 12]),
    'phone'             => '+255700654321',
    'avatar'            => 'default.png',
    'bio'               => 'Health-conscious home chef passionate about vegan Tanzanian cuisine.',
    'status'            => 'active',
    'email_verified_at' => date('Y-m-d H:i:s'),
]);
log_ok("Customer 2  →  ID: {$user2Id}  |  samson2@mealkit.com  /  User@1234");

/* ============================================================
   SEED 3 — SUBSCRIPTION PLANS
   ============================================================ */
log_head('📦 Seeding Subscription Plans');

$planFreeId = db_insert($pdo, 'subscription_plans', [
    'name'               => 'Free',
    'slug'               => 'free',
    'description'        => 'Get started with 5 recipes per month at no cost.',
    'price'              => 0.00,
    'duration_days'      => 30,
    'recipe_limit'       => 5,
    'meal_plan_limit'    => 1,
    'can_download'       => 0,
    'can_access_premium' => 0,
    'features'           => json_encode([
        '5 recipes per month',
        '1 meal plan',
        'Browse public recipes',
        'Community access',
    ]),
    'is_popular' => 0,
    'status'     => 'active',
]);
log_ok("Plan: Free  →  TSh 0 / month  |  ID: {$planFreeId}");

$planMonthlyId = db_insert($pdo, 'subscription_plans', [
    'name'               => 'Monthly',
    'slug'               => 'monthly',
    'description'        => 'Unlock unlimited recipes, PDF downloads, and up to 5 meal plans.',
    'price'              => 10000,
    'duration_days'      => 30,
    'recipe_limit'       => 0,
    'meal_plan_limit'    => 5,
    'can_download'       => 1,
    'can_access_premium' => 0,
    'features'           => json_encode([
        'Unlimited public recipes',
        '5 meal plans per month',
        'PDF recipe download',
        'Serving size calculator',
        'Email support',
    ]),
    'is_popular' => 1,
    'status'     => 'active',
]);
log_ok("Plan: Monthly  →  TSh 10,000 / month  |  ID: {$planMonthlyId}");

$planYearlyId = db_insert($pdo, 'subscription_plans', [
    'name'               => 'Yearly',
    'slug'               => 'yearly',
    'description'        => 'Everything in Monthly plus exclusive premium recipes and unlimited meal plans. Save 20% with annual billing.',
    'price'              => 100000,
    'duration_days'      => 365,
    'recipe_limit'       => 0,
    'meal_plan_limit'    => 0,
    'can_download'       => 1,
    'can_access_premium' => 1,
    'features'           => json_encode([
        'Everything in Monthly',
        'Exclusive premium recipes',
        'Unlimited meal plans',
        'Priority support',
        'Early access to new recipes',
        'Monthly nutrition report',
        'Save 20% annually',
    ]),
    'is_popular' => 0,
    'status'     => 'active',
]);
log_ok("Plan: Yearly  →  TSh 100,000 / year  |  ID: {$planYearlyId}");

/* ============================================================
   SEED 4 — CATEGORIES
   ============================================================ */
log_head('🗂️ Seeding Recipe Categories');

$categories = [
    ['name' => 'Breakfast',       'slug' => 'breakfast',       'description' => 'Start your day right with hearty breakfast recipes.'],
    ['name' => 'Lunch',           'slug' => 'lunch',           'description' => 'Satisfying midday meals for home or office.'],
    ['name' => 'Dinner',          'slug' => 'dinner',          'description' => 'Delicious dinner recipes for the whole family.'],
    ['name' => 'Vegan',           'slug' => 'vegan',           'description' => 'Plant-based recipes free from all animal products.'],
    ['name' => 'Desserts',        'slug' => 'desserts',        'description' => 'Sweet treats, cakes, and confectionery.'],
    ['name' => 'Soups & Stews',   'slug' => 'soups-stews',     'description' => 'Warm, comforting soups and hearty stews.'],
    ['name' => 'Grills & BBQ',    'slug' => 'grills-bbq',      'description' => 'Perfectly charred grilled meats, fish, and vegetables.'],
    ['name' => 'Healthy & Salads','slug' => 'healthy-salads',  'description' => 'Light, nutritious salads and healthy bowl recipes.'],
];

$catIds = [];
foreach ($categories as $cat) {
    $id = db_insert($pdo, 'categories', [
        'admin_id'    => $adminId,
        'name'        => $cat['name'],
        'slug'        => $cat['slug'],
        'description' => $cat['description'],
        'status'      => 'active',
    ]);
    $catIds[$cat['slug']] = $id;
    log_ok("Category: {$cat['name']}  →  ID: {$id}");
}

/* ============================================================
   SEED 5 — RECIPES  (6 full recipes with ingredients + procedures)
   ============================================================ */
log_head('🍽️ Seeding Recipes');

// ── Helper to seed one complete recipe ──────────────────────────────────────
function seedRecipe(
    PDO    $pdo,
    int    $adminId,
    int    $categoryId,
    array  $recipe,
    array  $ingredients,
    array  $procedures
): int {
    // Insert recipe
    $recipeId = db_insert($pdo, 'recipes', [
        'admin_id'    => $adminId,
        'category_id' => $categoryId,
        'title'       => $recipe['title'],
        'slug'        => $recipe['slug'],
        'description' => $recipe['description'],
        'prep_time'   => $recipe['prep_time'],
        'cook_time'   => $recipe['cook_time'],
        'servings'    => $recipe['servings'],
        'difficulty'  => $recipe['difficulty'],
        'calories'    => $recipe['calories'] ?? null,
        'is_premium'  => $recipe['is_premium']  ?? 0,
        'is_featured' => $recipe['is_featured'] ?? 0,
        'status'      => 'published',
    ]);

    // Insert ingredients
    foreach ($ingredients as $order => $ing) {
        db_insert($pdo, 'ingredients', [
            'recipe_id'  => $recipeId,
            'name'       => $ing['name'],
            'quantity'   => $ing['quantity'],
            'unit'       => $ing['unit'] ?? null,
            'sort_order' => $order + 1,
        ]);
    }

    // Insert procedures
    foreach ($procedures as $step => $proc) {
        db_insert($pdo, 'procedures', [
            'recipe_id'   => $recipeId,
            'step_number' => $step + 1,
            'instruction' => $proc['instruction'],
            'tip'         => $proc['tip'] ?? null,
        ]);
    }

    return $recipeId;
}

// ── Recipe 1: Ghanaian Jollof Rice ─────────────────────────────────────────
$r1Id = seedRecipe(
    $pdo, $adminId, $catIds['dinner'],
    [
        'title'       => 'Ghanaian Jollof Rice',
        'slug'        => 'ghanaian-jollof-rice',
        'description' => 'The iconic West African one-pot rice dish, bursting with tomato, spice, and smoky depth. Ghana\'s Jollof is rich, lightly smoky from the bottom-pot effect, and pairs beautifully with chicken, fish, or fried plantain.',
        'prep_time'   => 20,
        'cook_time'   => 50,
        'servings'    => 6,
        'difficulty'  => 'medium',
        'calories'    => 420,
        'is_featured' => 1,
    ],
    [
        ['name' => 'Long grain parboiled rice',   'quantity' => '3',   'unit' => 'cups'],
        ['name' => 'Fresh tomatoes',               'quantity' => '5',   'unit' => 'medium'],
        ['name' => 'Red bell peppers',             'quantity' => '2',   'unit' => 'large'],
        ['name' => 'Scotch bonnet peppers',        'quantity' => '2',   'unit' => null],
        ['name' => 'Large onions',                 'quantity' => '2',   'unit' => null],
        ['name' => 'Tomato paste',                 'quantity' => '3',   'unit' => 'tbsp'],
        ['name' => 'Chicken stock or bouillon',    'quantity' => '2',   'unit' => 'cups'],
        ['name' => 'Vegetable oil',                'quantity' => '1/4', 'unit' => 'cup'],
        ['name' => 'Curry powder',                 'quantity' => '1',   'unit' => 'tsp'],
        ['name' => 'Dried thyme',                  'quantity' => '1',   'unit' => 'tsp'],
        ['name' => 'Bay leaves',                   'quantity' => '2',   'unit' => null],
        ['name' => 'Salt and black pepper',        'quantity' => 'to taste', 'unit' => null],
    ],
    [
        ['instruction' => 'Blend the tomatoes, red bell peppers, scotch bonnet, and one onion into a smooth purée. Set aside.',
         'tip'         => 'For a richer colour, roast the tomatoes for 10 minutes before blending.'],
        ['instruction' => 'Rinse the rice under cold water until the water runs clear. This removes excess starch and prevents clumping. Drain and set aside.',
         'tip'         => null],
        ['instruction' => 'Heat the vegetable oil in a heavy-bottomed pot over medium heat. Slice the remaining onion and fry for 3–4 minutes until softened and golden.',
         'tip'         => null],
        ['instruction' => 'Add the tomato paste and stir-fry for 2 minutes until it darkens slightly. Pour in the blended tomato purée. Cook uncovered, stirring occasionally, for 20–25 minutes until the oil rises to the surface and the raw tomato smell disappears.',
         'tip'         => 'This "frying" stage is critical – do not rush it.'],
        ['instruction' => 'Pour in the chicken stock and season with curry powder, thyme, bay leaves, salt, and pepper. Bring to a gentle boil.',
         'tip'         => null],
        ['instruction' => 'Add the drained rice and stir to combine thoroughly. Reduce the heat to low, cover tightly with aluminium foil and then the lid (the foil traps steam perfectly).',
         'tip'         => 'The foil steam-seal is the secret to perfectly cooked Jollof rice.'],
        ['instruction' => 'Cook for 30–35 minutes, checking every 10 minutes and stirring from the bottom to prevent burning. Add a splash of water if too dry.',
         'tip'         => null],
        ['instruction' => 'Once the rice is fully cooked and the liquid absorbed, remove from heat. Let it rest for 5 minutes, then fluff with a fork. Remove bay leaves and serve hot.',
         'tip'         => 'The slightly charred bottom layer (the "party rice" crust) is a sought-after bonus!'],
    ]
);
log_ok("Recipe 1: Ghanaian Jollof Rice  →  ID: {$r1Id}");

// ── Recipe 2: Kelewele (Spiced Fried Plantain) ─────────────────────────────
$r2Id = seedRecipe(
    $pdo, $adminId, $catIds['breakfast'],
    [
        'title'       => 'Kelewele – Ghanaian Spiced Plantain',
        'slug'        => 'kelewele-ghanaian-spiced-plantain',
        'description' => 'Kelewele is a beloved Ghanaian street snack of ripe plantain chunks tossed in a bold ginger-pepper spice blend and deep-fried to golden, crispy perfection. Enjoy as a snack, side dish, or breakfast accompaniment.',
        'prep_time'   => 15,
        'cook_time'   => 20,
        'servings'    => 4,
        'difficulty'  => 'easy',
        'calories'    => 280,
        'is_featured' => 1,
    ],
    [
        ['name' => 'Very ripe plantains',        'quantity' => '3',   'unit' => 'large'],
        ['name' => 'Fresh ginger',               'quantity' => '1.5', 'unit' => 'inch piece'],
        ['name' => 'Scotch bonnet pepper',       'quantity' => '1',   'unit' => null],
        ['name' => 'Garlic cloves',              'quantity' => '2',   'unit' => null],
        ['name' => 'Ground nutmeg',              'quantity' => '1/4', 'unit' => 'tsp'],
        ['name' => 'Ground cayenne pepper',      'quantity' => '1/2', 'unit' => 'tsp'],
        ['name' => 'Anise seeds (optional)',     'quantity' => '1/4', 'unit' => 'tsp'],
        ['name' => 'Salt',                       'quantity' => '1/2', 'unit' => 'tsp'],
        ['name' => 'Vegetable oil for frying',  'quantity' => '2',   'unit' => 'cups'],
    ],
    [
        ['instruction' => 'Peel the ripe plantains and cut into 1-inch diagonal chunks or cubes. Set aside in a large bowl.',
         'tip'         => 'The darker and riper the plantain, the sweeter and more caramelised the Kelewele will be.'],
        ['instruction' => 'Blend the fresh ginger, garlic, and scotch bonnet with 2 tablespoons of water into a fine paste.',
         'tip'         => null],
        ['instruction' => 'Pour the spice paste over the plantain chunks. Add cayenne, nutmeg, anise seeds, and salt. Toss thoroughly to coat every piece evenly.',
         'tip'         => null],
        ['instruction' => 'Allow the plantains to marinate for at least 15 minutes so the spices penetrate deeply.',
         'tip'         => 'Marinating for 30 minutes gives an even bolder flavour.'],
        ['instruction' => 'Heat the oil in a deep pan or wok to 175°C (350°F). Test with a small piece of plantain – it should sizzle immediately.',
         'tip'         => 'Maintain the oil temperature throughout frying. Too hot = burnt outside; too cool = greasy.'],
        ['instruction' => 'Fry the plantain pieces in small batches (do not crowd the pan) for 3–4 minutes per side until deep golden brown and slightly crispy on the outside.',
         'tip'         => null],
        ['instruction' => 'Remove with a slotted spoon and drain on paper towels. Serve immediately as a snack, alongside groundnut soup, or with roasted peanuts.',
         'tip'         => null],
    ]
);
log_ok("Recipe 2: Kelewele  →  ID: {$r2Id}");

// ── Recipe 3: Groundnut Soup ────────────────────────────────────────────────
$r3Id = seedRecipe(
    $pdo, $adminId, $catIds['soups-stews'],
    [
        'title'       => 'Authentic Ghanaian Groundnut Soup',
        'slug'        => 'authentic-ghanaian-groundnut-soup',
        'description' => 'A deeply flavourful, protein-rich peanut-based soup slow-cooked with chicken, garden eggs, and a bold tomato-pepper base. Served with fufu, banku, or rice, this is quintessential Ghanaian comfort food.',
        'prep_time'   => 25,
        'cook_time'   => 70,
        'servings'    => 6,
        'difficulty'  => 'medium',
        'calories'    => 510,
        'is_featured' => 1,
    ],
    [
        ['name' => 'Chicken pieces (skin-on)',    'quantity' => '1.2', 'unit' => 'kg'],
        ['name' => 'Raw groundnut (peanut) paste','quantity' => '1',   'unit' => 'cup'],
        ['name' => 'Fresh tomatoes',              'quantity' => '4',   'unit' => 'medium'],
        ['name' => 'Large onion',                 'quantity' => '1',   'unit' => null],
        ['name' => 'Scotch bonnet peppers',       'quantity' => '2',   'unit' => null],
        ['name' => 'Tomato paste',                'quantity' => '2',   'unit' => 'tbsp'],
        ['name' => 'African garden eggs',         'quantity' => '4',   'unit' => 'medium'],
        ['name' => 'Water',                       'quantity' => '5',   'unit' => 'cups'],
        ['name' => 'Shrimp powder (optional)',    'quantity' => '1',   'unit' => 'tsp'],
        ['name' => 'Mixed spices (all-purpose)',  'quantity' => '1',   'unit' => 'tsp'],
        ['name' => 'Salt and black pepper',       'quantity' => 'to taste', 'unit' => null],
    ],
    [
        ['instruction' => 'Season chicken with salt, pepper, mixed spices, and half the onion (sliced). Add 1 cup of water and cook in a covered pot for 20–25 minutes until tender. Remove chicken; strain and reserve the stock.',
         'tip'         => 'Do not discard the stock – it forms the soup base.'],
        ['instruction' => 'Blend tomatoes, remaining onion, and scotch bonnet into a smooth purée. Set aside.',
         'tip'         => null],
        ['instruction' => 'Dissolve the groundnut paste in 1½ cups of warm water, stirring vigorously until smooth and lump-free.',
         'tip'         => 'Use natural, unsweetened groundnut paste (not commercial peanut butter with additives).'],
        ['instruction' => 'In a large pot, combine the reserved chicken stock with the dissolved groundnut liquid. Bring to a gentle boil over medium heat, stirring constantly to prevent the groundnut from catching at the bottom.',
         'tip'         => null],
        ['instruction' => 'Add the blended tomato purée and tomato paste. Stir well and continue to cook at a gentle simmer for 15 minutes.',
         'tip'         => null],
        ['instruction' => 'Add the cooked chicken pieces, whole garden eggs, and shrimp powder. Season with salt and pepper.',
         'tip'         => 'Whole garden eggs are traditional – they add texture and mild bitterness.'],
        ['instruction' => 'Simmer on medium-low heat for 30–35 minutes, stirring occasionally, until the soup thickens beautifully and the golden oil from the groundnut floats on the surface.',
         'tip'         => 'The floating oil indicates the soup is fully cooked. Skim off excess if desired.'],
        ['instruction' => 'Adjust seasoning. Remove and discard garden egg skins if preferred. Serve hot with fufu, banku, or steamed rice.',
         'tip'         => null],
    ]
);
log_ok("Recipe 3: Groundnut Soup  →  ID: {$r3Id}");

// ── Recipe 4: Garden Egg Stew (Vegan) ─────────────────────────────────────
$r4Id = seedRecipe(
    $pdo, $adminId, $catIds['vegan'],
    [
        'title'       => 'Garden Egg Stew (Vegan)',
        'slug'        => 'garden-egg-stew-vegan',
        'description' => 'A vibrant, hearty vegan stew made with African garden eggs (white eggplant) slow-cooked in a rich tomato-pepper sauce. Naturally plant-based, packed with fibre, and delicious served over rice or with boiled yam.',
        'prep_time'   => 15,
        'cook_time'   => 35,
        'servings'    => 4,
        'difficulty'  => 'easy',
        'calories'    => 195,
        'is_featured' => 0,
    ],
    [
        ['name' => 'African garden eggs (white eggplant)', 'quantity' => '6', 'unit' => 'medium'],
        ['name' => 'Fresh tomatoes',                       'quantity' => '4', 'unit' => 'medium'],
        ['name' => 'Red bell peppers',                     'quantity' => '2', 'unit' => null],
        ['name' => 'Large onion',                          'quantity' => '1', 'unit' => null],
        ['name' => 'Scotch bonnet pepper',                 'quantity' => '1', 'unit' => null],
        ['name' => 'Tomato paste',                         'quantity' => '1', 'unit' => 'tbsp'],
        ['name' => 'Vegetable oil',                        'quantity' => '3', 'unit' => 'tbsp'],
        ['name' => 'Mixed seasoning / all-purpose spice',  'quantity' => '1', 'unit' => 'tsp'],
        ['name' => 'Salt',                                 'quantity' => 'to taste', 'unit' => null],
        ['name' => 'Fresh basil or parsley (to garnish)',  'quantity' => 'handful', 'unit' => null],
    ],
    [
        ['instruction' => 'Wash the garden eggs and boil in lightly salted water for 10–12 minutes until tender when pierced with a fork. Drain, cool slightly, then peel and cut into chunks.',
         'tip'         => 'Don\'t overcook – they should be soft but hold their shape.'],
        ['instruction' => 'Blend tomatoes, red bell peppers, half the onion, and scotch bonnet into a smooth purée.',
         'tip'         => null],
        ['instruction' => 'Dice the remaining onion. Heat the vegetable oil in a wide pan over medium heat. Fry the diced onion for 3 minutes until golden.',
         'tip'         => null],
        ['instruction' => 'Add the tomato paste and stir-fry for 1 minute. Pour in the blended tomato purée. Cook uncovered, stirring frequently, for 15–20 minutes until the stew reduces and the oil floats to the surface.',
         'tip'         => 'The longer you cook the base, the richer and less acidic the stew will taste.'],
        ['instruction' => 'Season with mixed spice and salt. Gently fold in the garden egg chunks, being careful not to mash them.',
         'tip'         => null],
        ['instruction' => 'Cook for a further 5 minutes on low heat to allow the garden eggs to absorb the stew flavours.',
         'tip'         => null],
        ['instruction' => 'Taste and adjust seasoning. Garnish with fresh basil or parsley. Serve with steamed rice, boiled yam, or as a dip with bread.',
         'tip'         => null],
    ]
);
log_ok("Recipe 4: Garden Egg Stew (Vegan)  →  ID: {$r4Id}");

// ── Recipe 5: Chocolate Lava Fondant (Premium) ─────────────────────────────
$r5Id = seedRecipe(
    $pdo, $adminId, $catIds['desserts'],
    [
        'title'       => 'Dark Chocolate Lava Fondant',
        'slug'        => 'dark-chocolate-lava-fondant',
        'description' => 'A restaurant-quality dessert with a perfectly set chocolate shell and a gloriously molten, flowing centre. Surprisingly simple to make at home, and guaranteed to impress every time.',
        'prep_time'   => 20,
        'cook_time'   => 13,
        'servings'    => 4,
        'difficulty'  => 'hard',
        'calories'    => 490,
        'is_premium'  => 1,
        'is_featured' => 1,
    ],
    [
        ['name' => 'Dark chocolate (70% cocoa)',  'quantity' => '200', 'unit' => 'g'],
        ['name' => 'Unsalted butter',             'quantity' => '100', 'unit' => 'g'],
        ['name' => 'Whole eggs',                  'quantity' => '2',   'unit' => null],
        ['name' => 'Egg yolks',                   'quantity' => '2',   'unit' => null],
        ['name' => 'Caster sugar',                'quantity' => '80',  'unit' => 'g'],
        ['name' => 'Plain (all-purpose) flour',   'quantity' => '40',  'unit' => 'g'],
        ['name' => 'Cocoa powder (for dusting)',  'quantity' => '2',   'unit' => 'tbsp'],
        ['name' => 'Vanilla extract',             'quantity' => '1',   'unit' => 'tsp'],
        ['name' => 'Pinch of salt',               'quantity' => '1',   'unit' => 'pinch'],
    ],
    [
        ['instruction' => 'Preheat the oven to 200°C (390°F / Gas 6). Generously butter four 150ml ramekins and dust the inside with cocoa powder, tapping out the excess. This ensures clean release.',
         'tip'         => 'Place the buttered ramekins in the freezer for 5 minutes for an even coating.'],
        ['instruction' => 'Break the chocolate into pieces and place in a heatproof bowl with the butter. Set the bowl over a saucepan of barely simmering water (bain-marie). Stir until completely melted and smooth. Remove from heat and cool for 5 minutes.',
         'tip'         => 'Do not let the base of the bowl touch the water or the chocolate may seize.'],
        ['instruction' => 'In a separate large bowl, whisk together the whole eggs, egg yolks, and caster sugar using an electric whisk for 3–4 minutes until the mixture is pale, thick, and doubled in volume.',
         'tip'         => null],
        ['instruction' => 'Pour the cooled melted chocolate mixture into the egg mixture. Fold gently with a spatula, using a figure-of-eight motion to keep the volume.',
         'tip'         => null],
        ['instruction' => 'Sift the flour and salt over the mixture. Fold again until just combined – a few streaks are fine; do not overmix or the fondants will be cakey instead of molten.',
         'tip'         => 'Overmixing at this stage is the most common mistake. Stop as soon as no flour is visible.'],
        ['instruction' => 'Stir in the vanilla extract. Divide the batter evenly among the prepared ramekins. Refrigerate for a minimum of 20 minutes (or up to 24 hours – great for dinner parties!).',
         'tip'         => null],
        ['instruction' => 'Place ramekins on a baking tray. Bake for 12–14 minutes – the edges should be set and pulling away from the sides, but the centre should still have a very slight wobble.',
         'tip'         => 'Every oven is different. Test with your first batch: 12 min = very molten; 14 min = just oozing.'],
        ['instruction' => 'Remove from oven and allow to rest in the ramekins for exactly 1 minute. Run a knife around the edge, place a plate on top, and confidently invert. Serve immediately with vanilla ice cream or fresh cream.',
         'tip'         => 'Speed is everything – molten fondant waits for no one!'],
    ]
);
log_ok("Recipe 5: Dark Chocolate Lava Fondant (PREMIUM)  →  ID: {$r5Id}");

// ── Recipe 6: Grilled Tilapia ───────────────────────────────────────────────
$r6Id = seedRecipe(
    $pdo, $adminId, $catIds['grills-bbq'],
    [
        'title'       => 'Perfectly Grilled Tilapia with Pepper Sauce',
        'slug'        => 'grilled-tilapia-pepper-sauce',
        'description' => 'Whole tilapia marinated in a bold blend of garlic, ginger, herbs, and lemon, then grilled to perfection with beautiful charred marks. Served with a spicy shito-inspired pepper sauce, this dish is a Ghanaian celebration staple.',
        'prep_time'   => 20,
        'cook_time'   => 25,
        'servings'    => 2,
        'difficulty'  => 'medium',
        'calories'    => 360,
        'is_featured' => 0,
    ],
    [
        ['name' => 'Whole tilapia fish (cleaned & scaled)', 'quantity' => '2',   'unit' => 'medium'],
        ['name' => 'Lemon juice',                           'quantity' => '3',   'unit' => 'tbsp'],
        ['name' => 'Garlic cloves',                         'quantity' => '4',   'unit' => null],
        ['name' => 'Fresh ginger',                          'quantity' => '1',   'unit' => 'inch piece'],
        ['name' => 'Scotch bonnet pepper',                  'quantity' => '1',   'unit' => null],
        ['name' => 'Dried thyme',                           'quantity' => '1',   'unit' => 'tsp'],
        ['name' => 'Dried rosemary',                        'quantity' => '1/2', 'unit' => 'tsp'],
        ['name' => 'Vegetable oil',                         'quantity' => '2',   'unit' => 'tbsp'],
        ['name' => 'Salt and black pepper',                 'quantity' => 'to taste', 'unit' => null],
        ['name' => 'Medium onion (for stuffing)',           'quantity' => '1',   'unit' => null],
        ['name' => 'Fresh green chillies (optional)',       'quantity' => '2',   'unit' => null],
        ['name' => 'Lemon slices (to serve)',               'quantity' => '4',   'unit' => null],
    ],
    [
        ['instruction' => 'Score the tilapia on both sides with 3–4 deep diagonal cuts to the bone. This allows the marinade to penetrate and ensures even cooking. Season generously inside the cavity and in the cuts with salt and pepper.',
         'tip'         => 'Thoroughly dry the fish with paper towels before marinating – moisture steams rather than grills.'],
        ['instruction' => 'Blend garlic, ginger, scotch bonnet, thyme, and rosemary into a smooth paste with the lemon juice and vegetable oil.',
         'tip'         => null],
        ['instruction' => 'Rub the marinade all over the fish – inside the cavity, over the skin, and deep into each scored cut. Stuff the cavity loosely with sliced onion and green chillies.',
         'tip'         => null],
        ['instruction' => 'Cover and refrigerate for a minimum of 30 minutes, or ideally 2–4 hours for maximum flavour penetration.',
         'tip'         => 'Overnight marinating is even better if you plan ahead.'],
        ['instruction' => 'Preheat an outdoor grill or griddle pan to medium-high heat. Brush the grill grates generously with oil to prevent sticking.',
         'tip'         => 'A very hot, well-oiled grill is the key to releasing the fish without tearing the skin.'],
        ['instruction' => 'Place the fish on the grill. Do NOT move it for the first 5 minutes. Grill for 10–12 minutes per side, basting once with the remaining marinade after flipping.',
         'tip'         => 'The fish is ready to flip when it releases from the grill naturally without sticking.'],
        ['instruction' => 'The fish is cooked when the flesh flakes easily with a fork and the skin is beautifully charred. Internal temperature should reach 63°C (145°F).',
         'tip'         => null],
        ['instruction' => 'Serve immediately with lemon slices, hot pepper sauce, and your choice of banku, kenkey, boiled yam, or steamed vegetables.',
         'tip'         => null],
    ]
);
log_ok("Recipe 6: Grilled Tilapia  →  ID: {$r6Id}");

/* ============================================================
   SEED 6 — SUBSCRIPTIONS
   ============================================================ */
log_head('🔔 Seeding Subscriptions');

$subStart = date('Y-m-d H:i:s');
$subEnd   = date('Y-m-d H:i:s', strtotime('+30 days'));

// Samson on Yearly plan
$sub1Id = db_insert($pdo, 'subscriptions', [
    'user_id'    => $user1Id,
    'plan_id'    => $planPremiumId,
    'status'     => 'active',
    'starts_at'  => $subStart,
    'ends_at'    => $subEnd,
    'auto_renew' => 1,
]);
log_ok("Subscription 1: Samson → Yearly  |  ID: {$sub1Id}");

// Samson on Monthly plan
$sub2Id = db_insert($pdo, 'subscriptions', [
    'user_id'    => $user2Id,
    'plan_id'    => $planBasicId,
    'status'     => 'active',
    'starts_at'  => $subStart,
    'ends_at'    => $subEnd,
    'auto_renew' => 0,
]);
log_ok("Subscription 2: Samson → Monthly  |  ID: {$sub2Id}");

/* ============================================================
   SEED 7 — PAYMENTS
   ============================================================ */
log_head('💳 Seeding Payment Records');

$pay1Id = db_insert($pdo, 'payments', [
    'user_id'         => $user1Id,
    'subscription_id' => $sub1Id,
    'transaction_ref' => 'MK-' . strtoupper(bin2hex(random_bytes(6))),
    'amount'          => 10000,
    'currency'        => 'TZS',
    'payment_method'  => 'card',
    'provider'        => 'Card',
    'card_data'       => json_encode(['last4' => '3456', 'expiry' => '12/25']),
    'status'          => 'success',
    'gateway_response'=> json_encode([
        'status'      => 'success',
        'message'     => 'Payment approved',
        'provider'    => 'Card',
        'reference'   => 'SANDBOX-CARD-001',
        'amount'      => '10000',
        'currency'    => 'TZS',
        'timestamp'   => date('c'),
    ]),
    'paid_at' => date('Y-m-d H:i:s'),
]);
log_ok("Payment 1: Samson – TSh 10,000 (Card, success)  |  ID: {$pay1Id}");

$pay2Id = db_insert($pdo, 'payments', [
    'user_id'         => $user2Id,
    'subscription_id' => $sub2Id,
    'transaction_ref' => 'MK-' . strtoupper(bin2hex(random_bytes(6))),
    'amount'          => 100000,
    'currency'        => 'TZS',
    'payment_method'  => 'card',
    'provider'        => 'Card',
    'card_data'       => json_encode(['last4' => '4321', 'expiry' => '12/25']),
    'status'          => 'success',
    'gateway_response'=> json_encode([
        'status'    => 'success',
        'message'   => 'Payment approved',
        'provider'  => 'Card',
        'reference' => 'SANDBOX-CARD-002',
        'amount'    => '100000',
        'currency'  => 'TZS',
        'timestamp' => date('c'),
    ]),
    'paid_at' => date('Y-m-d H:i:s'),
]);
log_ok("Payment 2: Samson – TSh 100,000 (Card, success)  |  ID: {$pay2Id}");

/* ============================================================
   SEED 8 — FAVOURITES
   ============================================================ */
log_head('❤️ Seeding Favourites');

$favPairs = [
    [$user1Id, $r1Id], [$user1Id, $r3Id], [$user1Id, $r5Id],
    [$user2Id, $r2Id], [$user2Id, $r4Id], [$user2Id, $r6Id],
];
foreach ($favPairs as [$uid, $rid]) {
    db_insert($pdo, 'favourites', ['user_id' => $uid, 'recipe_id' => $rid]);
}
log_ok('6 favourite records created across both users');

/* ============================================================
   SEED 9 — MEAL PLANS + MEAL PLAN RECIPES
   ============================================================ */
log_head('📅 Seeding Meal Plans');

// Samson's weekly meal plan
$weekStart = date('Y-m-d', strtotime('last Monday'));
$weekEnd   = date('Y-m-d', strtotime('last Monday +6 days'));

$mp1Id = db_insert($pdo, 'meal_plans', [
    'user_id'     => $user1Id,
    'name'        => 'My Week 1 Plan',
    'description' => 'A balanced weekly plan mixing Ghanaian classics and healthy options.',
    'week_start'  => $weekStart,
    'week_end'    => $weekEnd,
    'status'      => 'active',
]);
log_ok("Meal Plan 1: Samson's Week 1  |  ID: {$mp1Id}");

// Assign recipes to meal slots
$mealSlots = [
    ['day_of_week' => 'Monday',    'meal_type' => 'breakfast', 'recipe_id' => $r2Id, 'servings' => 2],
    ['day_of_week' => 'Monday',    'meal_type' => 'dinner',    'recipe_id' => $r1Id, 'servings' => 4],
    ['day_of_week' => 'Tuesday',   'meal_type' => 'lunch',     'recipe_id' => $r4Id, 'servings' => 2],
    ['day_of_week' => 'Wednesday', 'meal_type' => 'dinner',    'recipe_id' => $r3Id, 'servings' => 4],
    ['day_of_week' => 'Thursday',  'meal_type' => 'breakfast', 'recipe_id' => $r2Id, 'servings' => 2],
    ['day_of_week' => 'Friday',    'meal_type' => 'dinner',    'recipe_id' => $r6Id, 'servings' => 2],
    ['day_of_week' => 'Saturday',  'meal_type' => 'dessert',   'recipe_id' => $r5Id, 'servings' => 4],
];
foreach ($mealSlots as $slot) {
    // meal_type 'dessert' must map to valid ENUM – coerce to 'snack' for Saturday dessert slot
    $mealType = $slot['meal_type'] === 'dessert' ? 'snack' : $slot['meal_type'];
    db_insert($pdo, 'meal_plan_recipes', [
        'meal_plan_id' => $mp1Id,
        'recipe_id'    => $slot['recipe_id'],
        'day_of_week'  => $slot['day_of_week'],
        'meal_type'    => $mealType,
        'servings'     => $slot['servings'],
    ]);
}
log_ok('7 meal plan recipe slots assigned to Samson\'s Week 1 plan');

/* ============================================================
   SEED 10 — NOTIFICATIONS
   ============================================================ */
log_head('🔔 Seeding Notifications');

$notifications = [
    // Samson
    [
        'user_id'  => $user1Id,
        'title'    => 'Welcome to MealKit! 🎉',
        'message'  => 'Your account is ready. Explore hundreds of recipes, build your first meal plan, and enjoy a seamless cooking experience.',
        'type'     => 'success',
        'category' => 'general',
        'action_url' => '/mealkit/customer/dashboard',
    ],
    [
        'user_id'  => $user1Id,
        'title'    => 'Premium Subscription Activated',
        'message'  => 'Your Premium subscription is now active. You have full access to all exclusive recipes and unlimited meal plans until ' . date('F j, Y', strtotime('+30 days')) . '.',
        'type'     => 'success',
        'category' => 'subscription',
        'action_url' => '/mealkit/subscriptions',
    ],
    [
        'user_id'  => $user1Id,
        'title'    => 'Payment Successful – GHS 99.99',
        'message'  => 'We received your payment of GHS 99.99 via MTN Mobile Money. Your subscription has been activated.',
        'type'     => 'info',
        'category' => 'payment',
        'action_url' => '/mealkit/payments',
    ],
    [
        'user_id'  => $user1Id,
        'title'    => 'New Recipe: Dark Chocolate Lava Fondant',
        'message'  => 'A stunning new premium dessert recipe has been added to your library. Perfect for date night!',
        'type'     => 'info',
        'category' => 'recipe',
        'action_url' => '/mealkit/recipes/view/' . $r5Id,
    ],
    // Akosua
    [
        'user_id'  => $user2Id,
        'title'    => 'Welcome to MealKit! 🎉',
        'message'  => 'Your account is ready. Start exploring our curated recipe collections and plan your week in style.',
        'type'     => 'success',
        'category' => 'general',
        'action_url' => '/mealkit/customer/dashboard',
    ],
    [
        'user_id'  => $user2Id,
        'title'    => 'Basic Subscription Activated',
        'message'  => 'Your Basic plan is active. Download unlimited recipe PDFs and manage up to 5 meal plans per month.',
        'type'     => 'success',
        'category' => 'subscription',
        'action_url' => '/mealkit/subscriptions',
    ],
    [
        'user_id'  => $user2Id,
        'title'    => 'Payment Successful – GHS 49.99',
        'message'  => 'Payment of GHS 49.99 via Vodafone Cash received and confirmed.',
        'type'     => 'info',
        'category' => 'payment',
        'action_url' => '/mealkit/payments',
    ],
    [
        'user_id'  => $user2Id,
        'title'    => 'Tip: Try the Serving Size Calculator',
        'message'  => 'Did you know you can scale any recipe instantly? Use the Serving Size Calculator on any recipe page.',
        'type'     => 'info',
        'category' => 'system',
        'action_url' => '/mealkit/recipes',
    ],
];

foreach ($notifications as $n) {
    db_insert($pdo, 'notifications', [
        'user_id'    => $n['user_id'],
        'title'      => $n['title'],
        'message'    => $n['message'],
        'type'       => $n['type'],
        'category'   => $n['category'],
        'is_read'    => 0,
        'action_url' => $n['action_url'],
    ]);
}
log_ok('8 notifications created (4 per user)');

/* ============================================================
   SEED 11 — RECIPE DOWNLOAD LOG
   ============================================================ */
log_head('📥 Seeding Recipe Download Logs');

$downloads = [
    ['user_id' => $user1Id, 'recipe_id' => $r1Id],
    ['user_id' => $user1Id, 'recipe_id' => $r5Id],
    ['user_id' => $user2Id, 'recipe_id' => $r4Id],
];
foreach ($downloads as $d) {
    db_insert($pdo, 'recipe_downloads', [
        'user_id'   => $d['user_id'],
        'recipe_id' => $d['recipe_id'],
        'ip_address' => '127.0.0.1',
    ]);
}
log_ok('3 download log records created');

// ── Re-enable FK checks ─────────────────────────────────────────────────────
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

/* ============================================================
   SEEDING COMPLETE — Summary
   ============================================================ */
?>
    <hr class="mt-4">
    <div class="alert alert-success">
      <h5 class="alert-heading">✅ Seeding Complete!</h5>
      <p class="mb-2">The database has been populated with demo data. You can now log in:</p>
      <table class="table table-sm table-bordered mb-0">
        <thead class="table-dark">
          <tr><th>Role</th><th>Email</th><th>Password</th><th>URL</th></tr>
        </thead>
        <tbody>
          <tr>
            <td><span class="badge bg-danger">Super Admin</span></td>
            <td><code>admin@mealkit.com</code></td>
            <td><code>Admin@123</code></td>
            <td><a href="/mealkit/auth/login">Login</a></td>
          </tr>
          <tr>
            <td><span class="badge bg-primary">Customer (Yearly)</span></td>
            <td><code>samson@mealkit.com</code></td>
            <td><code>User@1234</code></td>
            <td><a href="/mealkit/auth/login">Login</a></td>
          </tr>
          <tr>
            <td><span class="badge bg-info text-dark">Customer (Monthly)</span></td>
            <td><code>samson2@mealkit.com</code></td>
            <td><code>User@1234</code></td>
            <td><a href="/mealkit/auth/login">Login</a></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="alert alert-warning mt-2">
      <strong>⚠️ Security Reminder:</strong>
      Delete <code>database/seed.php</code> from your server before going to production.
    </div>
  </div><!-- /card-body -->
</div><!-- /card -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

