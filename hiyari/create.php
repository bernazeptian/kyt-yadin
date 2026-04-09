<?php
session_start();
require_once '../config/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit;
}

// Fetch departments from DB
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

// Fetch locations from DB
$locations = $pdo->query("SELECT id, name FROM locations ORDER BY name")->fetchAll();

// Auto-generate report number
$year = date('Y');
$month = date('m');
$count = $pdo->query("SELECT COUNT(*) FROM hiyari_reports WHERE YEAR(created_at) = $year AND MONTH(created_at) = $month")->fetchColumn();
$report_number = 'HH-' . $year . $month . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

// Error message if redirected back
$error = isset($_GET['error']) ? $_GET['error'] : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>New Report — Hiyari Hatto</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="../assets/dashboard.css" />
  <link rel="stylesheet" href="../assets/create.css" />
</head>

<body>

  <!-- ═══════════════════════════════════════
       SIDEBAR
  ═══════════════════════════════════════ -->
  <aside class="sidebar">

    <div class="sidebar__logo">
      <img src="../assets/logo.png" alt="Yanmar" class="sidebar__logo-img" />
    </div>

    <nav class="sidebar__nav">
      <a href="../index" class="nav-item nav-item" data-tooltip="Dashboard">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7" rx="1" />
          <rect x="14" y="3" width="7" height="7" rx="1" />
          <rect x="3" y="14" width="7" height="7" rx="1" />
          <rect x="14" y="14" width="7" height="7" rx="1" />
        </svg>
      </a>
      <a href="index" class="nav-item nav-item--active" data-tooltip="Hiyari Hatto">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          <line x1="12" y1="9" x2="12" y2="13" />
          <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
      </a>
      <a href="kyt/index" class="nav-item" data-tooltip="KYT">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 11l3 3L22 4" />
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
        </svg>
      </a>
      <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 1): ?>
        <a href="master/index" class="nav-item" data-tooltip="Master Data">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <line x1="8" y1="6" x2="21" y2="6" />
            <line x1="8" y1="12" x2="21" y2="12" />
            <line x1="8" y1="18" x2="21" y2="18" />
            <line x1="3" y1="6" x2="3.01" y2="6" />
            <line x1="3" y1="12" x2="3.01" y2="12" />
            <line x1="3" y1="18" x2="3.01" y2="18" />
          </svg>
        </a>
      <?php endif; ?>
    </nav>

    <div class="sidebar__bottom">
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="../auth/logout" class="nav-item nav-item--logout" data-tooltip="Sign Out">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <polyline points="16 17 21 12 16 7" />
            <line x1="21" y1="12" x2="9" y2="12" />
          </svg>
        </a>
      <?php else: ?>
        <a href="../auth/login" class="nav-item" data-tooltip="Sign In">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
            <polyline points="10 17 15 12 10 7" />
            <line x1="15" y1="12" x2="3" y2="12" />
          </svg>
        </a>
      <?php endif; ?>
    </div>

  </aside>

  <!-- ═══════════════════════════════════════
       MAIN
  ═══════════════════════════════════════ -->
  <main class="main">

    <header class="topbar">
      <div class="topbar__left">
        <a href="index.php" class="back-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <polyline points="15 18 9 12 15 6" />
          </svg>
        </a>
        <div>
          <h1 class="topbar__title">New Hiyari Hatto Report</h1>
          <p class="topbar__sub">Fill in the incident details below</p>
        </div>
      </div>
      <div class="topbar__right">
        <div class="topbar__date"><?php echo date('l, d F Y'); ?></div>
        <div class="topbar__avatar" title="<?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>">
          <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>
        </div>
      </div>
    </header>

    <div class="content">

      <?php if ($error): ?>
        <div class="alert alert--error">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          <?php
          $messages = [
            '1' => 'Something went wrong. Please try again.',
            'db' => 'Database error. Please contact the administrator.',
            'upload' => 'File upload failed. Please check your file and try again.',
          ];
          echo $messages[$error] ?? 'An error occurred.';
          ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="store.php" enctype="multipart/form-data" id="reportForm">

        <div class="form-grid">

          <!-- ══════════════════════════════
               LEFT COLUMN
          ══════════════════════════════ -->
          <div class="form-col">

            <!-- Report Information -->
            <div class="form-section">
              <div class="form-section__header">
                <div class="form-section__icon form-section__icon--blue">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                  </svg>
                </div>
                <h2 class="form-section__title">Report Information</h2>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Report Number</label>
                  <input type="text" class="form-input form-input--readonly" name="report_number"
                    value="<?php echo htmlspecialchars($report_number); ?>" readonly />
                  <span class="form-hint">Auto-generated</span>
                </div>
                <div class="form-group">
                  <label class="form-label" for="report_date">Report Date <span class="required">*</span></label>
                  <input type="date" class="form-input" id="report_date" name="report_date"
                    value="<?php echo date('Y-m-d'); ?>" required />
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="department_id">Department <span class="required">*</span></label>
                  <select class="form-select" id="department_id" name="department_id" required>
                    <option value="">-- Select Department --</option>
                    <?php foreach ($departments as $dept): ?>
                      <option value="<?php echo $dept['id']; ?>">
                        <?php echo htmlspecialchars($dept['name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label" for="location_id">Location <span class="required">*</span></label>
                  <select class="form-select" id="location_id" name="location_id" required>
                    <option value="">-- Select Location --</option>
                    <?php foreach ($locations as $loc): ?>
                      <option value="<?php echo $loc['id']; ?>">
                        <?php echo htmlspecialchars($loc['name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Category -->
              <div class="form-group">
                <label class="form-label">Category <span class="required">*</span></label>
                <div class="category-group">
                  <label class="category-option">
                    <input type="radio" name="category" value="near_miss" required />
                    <span class="category-btn">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"
                        height="16">
                        <path
                          d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                        <line x1="12" y1="9" x2="12" y2="13" />
                        <line x1="12" y1="17" x2="12.01" y2="17" />
                      </svg>
                      Near Miss (Hiyari Hatto)
                    </span>
                  </label>
                  <label class="category-option">
                    <input type="radio" name="category" value="unsafe_act" />
                    <span class="category-btn">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"
                        height="16">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                      </svg>
                      Unsafe Act
                    </span>
                  </label>
                  <label class="category-option">
                    <input type="radio" name="category" value="unsafe_condition" />
                    <span class="category-btn">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"
                        height="16">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        <polyline points="9 22 9 12 15 12 15 22" />
                      </svg>
                      Unsafe Condition
                    </span>
                  </label>
                </div>
              </div>

              <!-- Description -->
              <div class="form-group">
                <label class="form-label" for="description">Description <span class="required">*</span></label>
                <textarea class="form-textarea" id="description" name="description" rows="5"
                  placeholder="Describe what happened, where, and how..." required></textarea>
              </div>

            </div><!-- /report information -->

            <!-- Photo Upload -->
            <div class="form-section">
              <div class="form-section__header">
                <div class="form-section__icon form-section__icon--yellow">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <circle cx="8.5" cy="8.5" r="1.5" />
                    <polyline points="21 15 16 10 5 21" />
                  </svg>
                </div>
                <h2 class="form-section__title">
                  Photos
                  <span class="form-section__optional">(optional)</span>
                </h2>
              </div>

              <div class="upload-area" id="uploadArea">
                <input type="file" id="photos" name="photos[]" multiple accept="image/*" class="upload-input" />
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="36" height="36"
                  class="upload-icon">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                  <polyline points="17 8 12 3 7 8" />
                  <line x1="12" y1="3" x2="12" y2="15" />
                </svg>
                <p class="upload-text">Drag & drop photos here or <span class="upload-link">browse</span></p>
                <p class="upload-hint">PNG, JPG up to 5MB each · Max 5 photos</p>
              </div>
              <div class="photo-preview" id="photoPreview"></div>

            </div><!-- /photos -->

          </div><!-- /left column -->

          <!-- ══════════════════════════════
               RIGHT COLUMN
          ══════════════════════════════ -->
          <div class="form-col">

            <div class="form-section form-section--sticky">
              <div class="form-section__header">
                <div class="form-section__icon form-section__icon--red">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                    <path
                      d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                  </svg>
                </div>
                <h2 class="form-section__title">Risk Assessment</h2>
              </div>

              <!-- Likelihood -->
              <div class="form-group">
                <label class="form-label">Likelihood <span class="required">*</span></label>
                <div class="risk-options">
                  <?php
                  $likelihoods = [
                    'A' => 'Almost Certain',
                    'B' => 'Likely',
                    'C' => 'Moderate',
                    'D' => 'Unlikely',
                    'E' => 'Rare',
                  ];
                  foreach ($likelihoods as $val => $label): ?>
                    <label class="risk-option">
                      <input type="radio" name="likelihood" value="<?php echo $val; ?>" <?php echo $val === 'A' ? 'required' : ''; ?> />
                      <span class="risk-btn">
                        <span class="risk-code"><?php echo $val; ?></span>
                        <span class="risk-label"><?php echo $label; ?></span>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Severity -->
              <div class="form-group">
                <label class="form-label">Severity <span class="required">*</span></label>
                <div class="risk-options">
                  <?php
                  $severities = [
                    1 => 'Insignificant',
                    2 => 'Minor',
                    3 => 'Moderate',
                    4 => 'Major',
                    5 => 'Catastrophic',
                  ];
                  foreach ($severities as $val => $label): ?>
                    <label class="risk-option">
                      <input type="radio" name="severity" value="<?php echo $val; ?>" <?php echo $val === 1 ? 'required' : ''; ?> />
                      <span class="risk-btn">
                        <span class="risk-code"><?php echo $val; ?></span>
                        <span class="risk-label"><?php echo $label; ?></span>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Hidden inputs for likelihood, severity, risk_level -->
              <input type="hidden" name="likelihood_val" id="likelihoodInput" />
              <input type="hidden" name="severity_val" id="severityInput" />
              <input type="hidden" name="risk_level" id="riskLevelInput" />

              <!-- Risk Level Result -->
              <div class="risk-result" id="riskResult">
                <div class="risk-result__placeholder" id="riskPlaceholder">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28">
                    <path
                      d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                  </svg>
                  <span>Select Likelihood &amp; Severity<br />to calculate risk level</span>
                </div>
                <div class="risk-result__badge hidden" id="riskBadge"></div>
              </div>

              <!-- Risk Matrix Reference -->
              <div class="matrix-wrap">
                <p class="matrix-title">Risk Matrix Reference</p>
                <div class="matrix-scroll">
                  <table class="matrix-table">
                    <thead>
                      <tr>
                        <th>L \ S</th>
                        <th>1</th>
                        <th>2</th>
                        <th>3</th>
                        <th>4</th>
                        <th>5</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $matrix = [
                        'A' => ['E', 'E', 'E', 'E', 'E'],
                        'B' => ['M', 'H', 'H', 'E', 'E'],
                        'C' => ['L', 'M', 'H', 'E', 'E'],
                        'D' => ['L', 'L', 'M', 'H', 'E'],
                        'E' => ['L', 'L', 'M', 'H', 'H'],
                      ];
                      $classMap = ['L' => 'matrix-l', 'M' => 'matrix-m', 'H' => 'matrix-h', 'E' => 'matrix-e'];
                      foreach ($matrix as $row => $cols): ?>
                        <tr>
                          <td class="matrix-row-label"><?php echo $row; ?></td>
                          <?php foreach ($cols as $s => $val): ?>
                            <td class="matrix-cell <?php echo $classMap[$val]; ?>" data-l="<?php echo $row; ?>"
                              data-s="<?php echo $s + 1; ?>">
                              <?php echo $val; ?>
                            </td>
                          <?php endforeach; ?>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>

            </div><!-- /risk assessment -->

          </div><!-- /right column -->

        </div><!-- /form-grid -->

        <!-- Form Actions -->
        <div class="form-actions">
          <a href="index.php" class="btn btn--cancel">Cancel</a>
          <button type="submit" class="btn btn--submit" id="submitBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
              <polyline points="17 21 17 13 7 13 7 21" />
              <polyline points="7 3 7 8 15 8" />
            </svg>
            Submit Report
          </button>
        </div>

      </form>
    </div><!-- /content -->
  </main>

  <script src="../assets/create.js"></script>
</body>

</html>