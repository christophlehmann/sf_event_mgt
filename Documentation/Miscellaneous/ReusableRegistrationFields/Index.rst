.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt

.. _reusable-registration-fields:

Reusable registration fields
---------------------------

We do it by changing the field type from IRRE to select.

.. important::

   This is not officially supported, but works like a charm as of version 4.3

TCA: Override field type
^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: php

   // File: EXT:your_site/Configuration/TCA/Overrides/tx_sfeventmgt_domain_model_event.php

   $GLOBALS['TCA']['tx_sfeventmgt_domain_model_event']['columns']['registration_fields'] = [
      'exclude' => $GLOBALS['TCA']['tx_sfeventmgt_domain_model_event']['columns']['registration_fields']['exclude'],
      'label' => $GLOBALS['TCA']['tx_sfeventmgt_domain_model_event']['columns']['registration_fields']['label'],
      'displayCond' => $GLOBALS['TCA']['tx_sfeventmgt_domain_model_event']['columns']['registration_fields']['displayCond'],
      'config' => [
         'type' => 'select',
         'renderType' => 'selectMultipleSideBySide',
         'foreign_table' => $GLOBALS['TCA']['tx_sfeventmgt_domain_model_event']['columns']['registration_fields']['config']['foreign_table'],
         'foreign_table_where' => 'AND tx_sfeventmgt_domain_model_registration_field.pid=###CURRENT_PID### AND tx_sfeventmgt_domain_model_registration_field.sys_language_uid IN (-1,0) ORDER BY tx_sfeventmgt_domain_model_registration_field.title',
         'MM' => 'tx_sfeventmgt_event_registrationfield_mm',
         'maxitems' => 9999,
         'enableMultiSelectFilterTextfield' => true,
         'fieldControl' => [
            'editPopup' => [
               'disabled' => false,
            ],
            'addRecord' => [
               'disabled' => false,
            ],
            'listModule' => [
               'disabled' => false,
            ],
         ],
      ],
      'l10n_mode' => 'exclude',
   ];

Add a new mm-table
^^^^^^^^^^^^^^^^^^

.. code-block:: mysql
   
   /* File: EXT:your_site/ext_tables.sql */

   CREATE TABLE tx_sfeventmgt_event_registrationfield_mm (
      uid_local int(11) unsigned DEFAULT '0' NOT NULL,
      uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
      sorting int(11) unsigned DEFAULT '0' NOT NULL,
      sorting_foreign int(11) unsigned DEFAULT '0' NOT NULL,

      KEY uid_local (uid_local),
      KEY uid_foreign (uid_foreign)
   );