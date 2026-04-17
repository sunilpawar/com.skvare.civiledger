/**
 * CiviLedger - Financial Audit & Integrity Extension
 * JavaScript — com.skvare.civiledger
 */

(function ($, CRM) {
  'use strict';

  // -----------------------------------------------------------------------
  // Utility helpers
  // -----------------------------------------------------------------------

  var CiviLedger = {

    baseUrl: CRM.url('civicrm/civiledger'),

    /**
     * Show an inline loading spinner inside a container.
     */
    showLoading: function ($container, msg) {
      msg = msg || 'Loading…';
      $container.html(
        '<div class="civiledger-loading">' +
        '<span class="civiledger-spinner"></span>' + msg +
        '</div>'
      );
    },

    /**
     * Format a number as currency using CRM locale.
     */
    formatMoney: function (amount, currency) {
      currency = currency || CRM.config.defaultCurrency || 'USD';
      return parseFloat(amount).toFixed(2);
    },

    /**
     * Confirm + AJAX repair for a single contribution.
     */
    repairContribution: function (contributionId, $row) {
      if (!confirm('Repair financial chain for Contribution #' + contributionId + '?\n\nThis will create any missing financial_item and entity_financial_trxn records.')) {
        return;
      }

      var $btn = $row.find('.btn-repair');
      $btn.prop('disabled', true).text('Repairing…');

      $.ajax({
        url:      CRM.url('civicrm/civiledger/chain-repair'),
        method:   'POST',
        dataType: 'json',
        data: {
          action:          'repair_one',
          contribution_id: contributionId,
        },
        success: function (response) {
          if (response.success) {
            $row.addClass('row-repaired');
            $btn.replaceWith('<span class="badge-ok">✓ Repaired</span>');
            CiviLedger.showRepairLog(response.log, $row);
            CiviLedger.updateIntegrityCounts(-1);
          } else {
            alert('Repair failed: ' + (response.error || 'Unknown error'));
            $btn.prop('disabled', false).text('Repair');
          }
        },
        error: function () {
          alert('Network error during repair. Please try again.');
          $btn.prop('disabled', false).text('Repair');
        }
      });
    },

    /**
     * Show repair log entries inline below a row.
     */
    showRepairLog: function (log, $row) {
      if (!log || !log.length) return;
      var html = '<tr class="repair-log-row"><td colspan="99"><div class="repair-log">';
      log.forEach(function (entry) {
        var type = Object.keys(entry)[0];
        html += '<div class="log-' + type + '">' +
          (type === 'fixed'   ? '✓ ' :
           type === 'skip'    ? '— ' :
           type === 'warning' ? '⚠ ' :
           type === 'error'   ? '✗ ' : '  ') +
          entry[type] + '</div>';
      });
      html += '</div></td></tr>';
      $row.after(html);
    },

    /**
     * Decrement an integrity counter badge in the page header.
     */
    updateIntegrityCounts: function (delta) {
      $('.integrity-total-count').each(function () {
        var current = parseInt($(this).text(), 10) || 0;
        var next = Math.max(0, current + delta);
        $(this).text(next);
        if (next === 0) {
          $(this).closest('.integrity-summary').removeClass('summary-bad').addClass('summary-good');
        }
      });
    },

    // -----------------------------------------------------------------------
    // Audit Trail: collapsible nodes
    // -----------------------------------------------------------------------

    initAuditTrail: function () {
      // Make layer titles collapsible
      $(document).on('click', '.audit-layer-title', function () {
        $(this).next().slideToggle(200);
        $(this).toggleClass('collapsed');
      });

      // Highlight a node when hovering related rows
      $(document).on('mouseenter', '.audit-node', function () {
        $(this).addClass('node-highlight');
      }).on('mouseleave', '.audit-node', function () {
        $(this).removeClass('node-highlight');
      });
    },

    // -----------------------------------------------------------------------
    // Account Correction Tool
    // -----------------------------------------------------------------------

    initAccountCorrection: function () {
      // Live preview of FROM/TO change
      $('#new_from_account_id, #new_to_account_id').on('change', function () {
        var fromName = $('#new_from_account_id option:selected').text();
        var toName   = $('#new_to_account_id option:selected').text();

        if (fromName && toName) {
          $('.correction-preview-from').text(fromName || '—');
          $('.correction-preview-to').text(toName   || '—');
          $('.correction-preview').show();
        }
      });

      // Require reason before submitting
      $('form').on('submit', function (e) {
        var reason = $('#reason').val();
        if ($('#new_from_account_id').length && !reason.trim()) {
          e.preventDefault();
          alert('A reason is required for account corrections (audit trail).');
          $('#reason').focus();
          return false;
        }
      });
    },

    // -----------------------------------------------------------------------
    // Contribution search autocomplete
    // -----------------------------------------------------------------------

    initContributionSearch: function () {
      var $input = $('#search_contribution_id');
      if (!$input.length) return;

      $input.on('keyup', CiviLedger.debounce(function () {
        var val = $input.val().trim();
        if (val.length < 2) return;

        $.ajax({
          url:      CRM.url('civicrm/civiledger/ajax'),
          data:     { op: 'search_contributions', term: val },
          dataType: 'json',
          success: function (data) {
            if (data.rows && data.rows.length) {
              CiviLedger.showSearchSuggestions(data.rows, $input);
            }
          }
        });
      }, 300));
    },

    showSearchSuggestions: function (rows, $input) {
      var $list = $('#contribution-suggestions');
      if (!$list.length) {
        $list = $('<ul id="contribution-suggestions" class="civiledger-suggestions"></ul>');
        $input.after($list);
      }
      $list.empty();
      rows.forEach(function (row) {
        $list.append(
          $('<li>').text('#' + row.id + ' — ' + row.contact_name + ' (' + row.total_amount + ')')
            .on('click', function () {
              $input.val(row.id);
              $list.hide();
            })
        );
      });
      $list.show();
    },

    // -----------------------------------------------------------------------
    // Batch repair: select all / deselect
    // -----------------------------------------------------------------------

    initBatchRepair: function () {
      $('#select-all-broken').on('change', function () {
        $('.broken-checkbox').prop('checked', this.checked);
        CiviLedger.updateBatchCount();
      });

      $(document).on('change', '.broken-checkbox', CiviLedger.updateBatchCount);

      $('#btn-batch-repair').on('click', function (e) {
        var ids = [];
        $('.broken-checkbox:checked').each(function () {
          ids.push($(this).val());
        });
        if (!ids.length) {
          alert('Please select at least one contribution to repair.');
          e.preventDefault();
          return;
        }
        if (!confirm('Repair ' + ids.length + ' contribution(s)?')) {
          e.preventDefault();
          return;
        }
        $('#batch_ids').val(ids.join(','));
      });
    },

    updateBatchCount: function () {
      var count = $('.broken-checkbox:checked').length;
      $('#batch-selected-count').text(count);
    },

    // -----------------------------------------------------------------------
    // Date filter: auto-submit on change
    // -----------------------------------------------------------------------

    initDateFilters: function () {
      var $form = $('.civiledger-filter-bar form');
      $form.find('input[type="date"]').on('change', function () {
        $form.submit();
      });
    },

    // -----------------------------------------------------------------------
    // Mismatch detector: expandable detail
    // -----------------------------------------------------------------------

    initMismatchDetail: function () {
      $(document).on('click', '.btn-mismatch-detail', function () {
        var $row = $(this).closest('tr');
        var $detail = $row.next('.mismatch-detail-row');
        if ($detail.length) {
          $detail.toggle();
        } else {
          var cid = $(this).data('cid');
          var $detailRow = $('<tr class="mismatch-detail-row"><td colspan="99">' +
            '<div class="civiledger-loading"><span class="civiledger-spinner"></span>Loading detail…</div>' +
            '</td></tr>');
          $row.after($detailRow);

          $.ajax({
            url:  CRM.url('civicrm/civiledger/ajax'),
            data: { op: 'mismatch_detail', cid: cid },
            dataType: 'json',
            success: function (data) {
              $detailRow.find('td').html(data.html || '<em>No detail available.</em>');
            },
            error: function () {
              $detailRow.find('td').html('<em>Failed to load detail.</em>');
            }
          });
        }
      });
    },

    // -----------------------------------------------------------------------
    // Utility: debounce
    // -----------------------------------------------------------------------

    debounce: function (fn, delay) {
      var timer;
      return function () {
        clearTimeout(timer);
        timer = setTimeout(fn.bind(this, arguments), delay);
      };
    }
  };

  // -----------------------------------------------------------------------
  // Bootstrap on document ready
  // -----------------------------------------------------------------------

  $(document).ready(function () {

    // Always init
    CiviLedger.initDateFilters();
    CiviLedger.initAuditTrail();

    // Page-specific inits based on body class
    var $body = $('body');

    if ($body.find('.civiledger-correction-page').length) {
      CiviLedger.initAccountCorrection();
    }

    if ($body.find('.civiledger-integrity-page').length) {
      // Wire up individual repair buttons
      $(document).on('click', '.btn-repair', function (e) {
        e.preventDefault();
        var cid  = $(this).data('cid');
        var $row = $(this).closest('tr');
        CiviLedger.repairContribution(cid, $row);
      });
    }

    if ($body.find('.civiledger-repair-page').length) {
      CiviLedger.initBatchRepair();
    }

    if ($body.find('.civiledger-correction-page').length ||
        $body.find('.civiledger-audit-page').length) {
      CiviLedger.initContributionSearch();
    }

    if ($body.find('.civiledger-mismatch-page').length) {
      CiviLedger.initMismatchDetail();
    }

    // ── Mismatch repair buttons ──────────────────────────────────────────────
    $(document).on('click', '.crm-mismatch-repair', function (e) {
      e.preventDefault();
      var $btn  = $(this);
      var op    = $btn.data('op');
      var cid   = $btn.data('cid');
      var ajax  = $btn.data('ajax');
      var label = $.trim($btn.text());

      if (!confirm('Apply fix: "' + label + '" for contribution #' + cid + '?\n\n' + ($btn.attr('title') || ''))) {
        return;
      }
      $btn.prop('disabled', true).text('Applying…');

      CRM.$.ajax({
        url: ajax,
        data: { op: op, cid: cid },
        dataType: 'json',
        success: function (resp) {
          if (resp && resp.success) {
            $btn.closest('td').html(
              '<span style="color:#28a745"><i class="crm-i fa-check-circle"></i> Fixed — reload to verify</span>'
            );
          } else {
            $btn.prop('disabled', false).text(label);
            alert('Repair failed: ' + ((resp && resp.error) ? resp.error : 'Unknown error'));
          }
        },
        error: function () {
          $btn.prop('disabled', false).text(label);
          alert('AJAX error — repair could not be applied.');
        }
      });
    });

    // Tooltip for .help-tip elements
    $('[title]').each(function () {
      $(this).attr('title', $(this).attr('title'));
    });

  });

  // Expose globally for inline onclick attributes
  window.CiviLedger = CiviLedger;

})(CRM.$, CRM);
