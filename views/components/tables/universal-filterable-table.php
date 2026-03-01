<?php

declare(strict_types=1);

if (!function_exists('render_universal_filterable_table_component')) {
    function render_universal_filterable_table_component(array $config): void
    {
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $sanitizeFilterKey = static function (string $key): string {
            $sanitized = preg_replace('/[^a-z0-9_-]/', '', strtolower($key));
            return $sanitized === null ? '' : $sanitized;
        };
        $normalizeText = static function (string $value): string {
            $collapsed = preg_replace('/\s+/', ' ', trim($value));
            $result = $collapsed === null ? trim($value) : $collapsed;
            return strtolower($result);
        };

        $componentId = (string) ($config['id'] ?? ('filterable-table-' . uniqid()));
        $title = (string) ($config['title'] ?? 'Data');
        $description = (string) ($config['description'] ?? '');
        $stats = is_array($config['stats'] ?? null) ? $config['stats'] : [];
        $action = is_array($config['action'] ?? null) ? $config['action'] : null;
        $columns = is_array($config['columns'] ?? null) ? $config['columns'] : [];
        $rows = is_array($config['rows'] ?? null) ? $config['rows'] : [];
        $filters = is_array($config['filters'] ?? null) ? $config['filters'] : [];
        $tabs = is_array($config['tabs'] ?? null) ? $config['tabs'] : [];
        $searchConfig = is_array($config['search'] ?? null) ? $config['search'] : [];
        $rowMetaBuilder = is_callable($config['rowMetaBuilder'] ?? null) ? $config['rowMetaBuilder'] : null;
        $emptyMessage = (string) ($config['emptyMessage'] ?? 'Data tidak ditemukan.');

        $tableCardClass = trim('table-card ' . (string) ($config['tableCardClass'] ?? ''));
        $tableHeaderClass = trim('table-card-header table-card-header-between ' . (string) ($config['tableHeaderClass'] ?? ''));
        $controlsClass = trim('table-card-controls ' . (string) ($config['controlsClass'] ?? ''));
        $tableContainerClass = trim('table-container ' . (string) ($config['tableContainerClass'] ?? ''));
        $tableClass = trim((string) ($config['tableClass'] ?? ''));
        $tableAriaLabel = (string) ($config['tableAriaLabel'] ?? $title);

        $searchEnabled = (bool) ($searchConfig['enabled'] ?? true);
        $searchPlaceholder = (string) ($searchConfig['placeholder'] ?? 'Cari data...');
        $searchLabel = (string) ($searchConfig['label'] ?? 'Pencarian');
        $showTools = $searchEnabled || !empty($filters) || !empty($tabs);
        $totalRows = count($rows);
        $colspan = max(1, count($columns));
        ?>
        <section class="<?= $escape($tableCardClass) ?>">
            <div class="<?= $escape($tableHeaderClass) ?>">
                <div>
                    <h3><?= $escape($title) ?></h3>
                    <?php if ($description !== ''): ?>
                        <p><?= $escape($description) ?></p>
                    <?php endif; ?>
                </div>
                <div class="<?= $escape($controlsClass) ?>">
                    <?php foreach ($stats as $stat):
                        $statLabel = (string) ($stat['label'] ?? '');
                        if ($statLabel === '') {
                            continue;
                        }
                        $statClass = trim('table-stat-chip ' . (string) ($stat['class'] ?? ''));
                        ?>
                        <span class="<?= $escape($statClass) ?>"><?= $escape($statLabel) ?></span>
                    <?php endforeach; ?>
                    <?php if (is_array($action)): ?>
                        <?php
                        $actionLabel = (string) ($action['label'] ?? '');
                        $actionHref = (string) ($action['href'] ?? '#');
                        $actionClass = trim('primary-action-btn ' . (string) ($action['class'] ?? ''));
                        ?>
                        <?php if ($actionLabel !== ''): ?>
                            <a class="<?= $escape($actionClass) ?>" href="<?= $escape($actionHref) ?>">
                                <?= $escape($actionLabel) ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($showTools): ?>
                <div class="table-tools" data-table-tools data-table-target="<?= $escape($componentId) ?>">
                    <?php if (!empty($tabs)): ?>
                        <div class="table-tools__tabs">
                            <?php foreach ($tabs as $tabGroup):
                                if (!is_array($tabGroup)) {
                                    continue;
                                }

                                $tabKey = (string) ($tabGroup['key'] ?? '');
                                $safeTabKey = $sanitizeFilterKey($tabKey);
                                if ($safeTabKey === '') {
                                    continue;
                                }

                                $tabOptions = is_array($tabGroup['options'] ?? null) ? $tabGroup['options'] : [];
                                $normalizedTabOptions = [];
                                foreach ($tabOptions as $tabOptionKey => $tabOptionValue) {
                                    $optionValue = '';
                                    $optionLabel = '';
                                    if (is_array($tabOptionValue)) {
                                        $optionValue = (string) ($tabOptionValue['value'] ?? '');
                                        $optionLabel = (string) ($tabOptionValue['label'] ?? $optionValue);
                                    } elseif (is_string($tabOptionKey)) {
                                        $optionValue = (string) $tabOptionKey;
                                        $optionLabel = (string) $tabOptionValue;
                                    } else {
                                        $optionValue = (string) $tabOptionValue;
                                        $optionLabel = (string) $tabOptionValue;
                                    }

                                    if ($optionValue === '' || $optionLabel === '') {
                                        continue;
                                    }

                                    $normalizedTabOptions[] = [
                                        'value' => $normalizeText($optionValue),
                                        'label' => $optionLabel,
                                    ];
                                }

                                if (empty($normalizedTabOptions)) {
                                    continue;
                                }

                                $tabLabel = (string) ($tabGroup['label'] ?? 'Tab');
                                $defaultTabValue = $normalizeText((string) ($tabGroup['defaultValue'] ?? $normalizedTabOptions[0]['value']));
                                $activeTabValue = $normalizedTabOptions[0]['value'];
                                foreach ($normalizedTabOptions as $tabOption) {
                                    if ((string) ($tabOption['value'] ?? '') === $defaultTabValue) {
                                        $activeTabValue = $defaultTabValue;
                                        break;
                                    }
                                }
                                ?>
                                <div class="table-tools__tab-group" data-table-tab-group="<?= $escape($safeTabKey) ?>">
                                    <span class="table-tools__tab-label"><?= $escape($tabLabel) ?></span>
                                    <div class="table-tools__tab-list" role="group" aria-label="<?= $escape($tabLabel) ?>">
                                        <?php foreach ($normalizedTabOptions as $tabOption):
                                            $tabOptionValue = (string) ($tabOption['value'] ?? '');
                                            $tabOptionLabel = (string) ($tabOption['label'] ?? '');
                                            if ($tabOptionValue === '' || $tabOptionLabel === '') {
                                                continue;
                                            }

                                            $isActive = ($tabOptionValue === $activeTabValue);
                                            ?>
                                            <button type="button"
                                                class="table-tools__tab-btn<?= $isActive ? ' is-active' : '' ?>"
                                                data-table-tab-key="<?= $escape($safeTabKey) ?>"
                                                data-table-tab-value="<?= $escape($tabOptionValue) ?>"
                                                aria-pressed="<?= $isActive ? 'true' : 'false' ?>">
                                                <?= $escape($tabOptionLabel) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="table-tools__inputs">
                        <?php if ($searchEnabled): ?>
                            <label class="table-tools__search">
                                <span><?= $escape($searchLabel) ?></span>
                                <input type="search" data-table-search placeholder="<?= $escape($searchPlaceholder) ?>">
                            </label>
                        <?php endif; ?>

                        <?php foreach ($filters as $filter):
                            $filterKey = (string) ($filter['key'] ?? '');
                            $safeFilterKey = $sanitizeFilterKey($filterKey);
                            if ($safeFilterKey === '') {
                                continue;
                            }
                            $filterLabel = (string) ($filter['label'] ?? $filterKey);
                            $filterOptions = is_array($filter['options'] ?? null) ? $filter['options'] : [];
                            $allLabel = (string) ($filter['allLabel'] ?? ('Semua ' . $filterLabel));
                            ?>
                            <label class="table-tools__filter">
                                <span><?= $escape($filterLabel) ?></span>
                                <select data-table-filter-key="<?= $escape($safeFilterKey) ?>">
                                    <option value=""><?= $escape($allLabel) ?></option>
                                    <?php foreach ($filterOptions as $optionKey => $optionValue):
                                        $value = '';
                                        $label = '';
                                        if (is_array($optionValue)) {
                                            $value = (string) ($optionValue['value'] ?? '');
                                            $label = (string) ($optionValue['label'] ?? $value);
                                        } elseif (is_string($optionKey)) {
                                            $value = (string) $optionKey;
                                            $label = (string) $optionValue;
                                        } else {
                                            $value = (string) $optionValue;
                                            $label = (string) $optionValue;
                                        }
                                        if ($value === '' || $label === '') {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?= $escape($normalizeText($value)) ?>"><?= $escape($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="table-tools__result">
                        Menampilkan
                        <strong data-table-visible-count><?= $escape((string) $totalRows) ?></strong>
                        dari
                        <strong data-table-total-count><?= $escape((string) $totalRows) ?></strong>
                        data
                    </p>
                </div>
            <?php endif; ?>

            <div class="<?= $escape($tableContainerClass) ?>">
                <table class="<?= $escape($tableClass) ?>" id="<?= $escape($componentId) ?>" data-universal-table
                    aria-label="<?= $escape($tableAriaLabel) ?>">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $column):
                                $label = (string) ($column['label'] ?? '');
                                ?>
                                <th><?= $escape($label) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody data-table-body>
                        <?php if (!empty($rows)): ?>
                            <?php foreach ($rows as $rowIndex => $row):
                                $meta = [];
                                if ($rowMetaBuilder !== null) {
                                    $metaResult = call_user_func($rowMetaBuilder, $row, $rowIndex);
                                    if (is_array($metaResult)) {
                                        $meta = $metaResult;
                                    }
                                }

                                $searchText = (string) ($meta['search'] ?? '');
                                if ($searchText === '' && is_array($row)) {
                                    $searchParts = [];
                                    foreach ($row as $value) {
                                        if (is_scalar($value) || $value === null) {
                                            $searchParts[] = (string) $value;
                                        }
                                    }
                                    $searchText = implode(' ', $searchParts);
                                }

                                $normalizedSearchText = $normalizeText($searchText);
                                $filterValues = is_array($meta['filters'] ?? null) ? $meta['filters'] : [];
                                ?>
                                <tr data-table-row data-table-search="<?= $escape($normalizedSearchText) ?>"
                                    <?php foreach ($filterValues as $key => $value):
                                        $safeKey = $sanitizeFilterKey((string) $key);
                                        if ($safeKey === '') {
                                            continue;
                                        }
                                        $normalizedValue = $normalizeText((string) $value);
                                        ?>
                                        data-table-filter-<?= $escape($safeKey) ?>="<?= $escape($normalizedValue) ?>"
                                    <?php endforeach; ?>>
                                    <?php foreach ($columns as $column):
                                        $cellClass = (string) ($column['cellClass'] ?? '');
                                        $renderCallback = $column['render'] ?? null;
                                        ?>
                                        <td<?= $cellClass !== '' ? ' class="' . $escape($cellClass) . '"' : '' ?>>
                                            <?php
                                            if (is_callable($renderCallback)) {
                                                echo (string) call_user_func($renderCallback, $row, $rowIndex);
                                            } else {
                                                $key = (string) ($column['key'] ?? '');
                                                $value = $key !== '' && is_array($row) ? ($row[$key] ?? '') : '';
                                                echo $escape((string) $value);
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr data-table-empty-static>
                                <td colspan="<?= $escape((string) $colspan) ?>" class="empty-cell"><?= $escape($emptyMessage) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php
    }
}
