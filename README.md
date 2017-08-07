Migrate RecipeML
================
Migrate RecipeML is a Drupal 8 module that provides plugins for the Migrate
module.  It will allow you to import recipes from a RecipeML XML file into the
nodes provided by the contrib module Recipe.  The module also provides a form
that will allow you to upload the file and run the import process through the
admin UI.  The import form is available at /admin/content/import-recipeml.

Regrettably, this module has a few problems which prevent me from wanting to
release it on Drupal.org or making it a submodule of Recipe.

1. Ideally, the migration should be configurable through the admin UI, so that
   different fields or an entirely different content or entity type could be
   used as a target.  Right now, the migration is dependent on the content type
   and fields that come with the Recipe module.  Although if you need to create
   a custom migration, then you can copy the configuration file in the
   migration directory and change the mappings to suit your needs.

   My hope is that eventually a configurable UI for migrations will be created.
   If that ever happens, the my plan is to build something that will integrate
   with that module's API.  Until then, this will have to suffice.

2. The migrations can be rolled back with the Migrate Tools module and Drush,
   which is good.  The bad part is that you can't roll back individual uploads.
   All imports are treated as part of the same migration.  That's just how
   migrations like this work.  That means if you need to roll back any upload,
   you'll be rolling back all of the imported uploads.

   That's another reason I want to integrate with another contrib migration
   module.  For instance, Migrate Plus stores migration configuration info as
   config entities.  That would enable individual uploads as different entities,
   which could then be managed separately.  Unfortunately, I wasn't able to
   generate those entities dynamically at this time.  It may be possible with
   more work.

3. This module will break some of the Migrate Tools commands for Drush.
   Notably, the migrate-status command will start throwing exceptions if you try
   to use it with this module enabled.  The problem is that the plugins need to
   have a file URI injected at run time.  The import form provides the plugins
   with the URI, but when they are instantiated separately then they don't get
   this important bit of configuration and cause errors to happen.  If you need
   to manage other migrations with Migrate Tools, then you should uninstall this
   module except when you need it.

All that said, this module will work to import RecipeML recipes into your Drupal
site.  It's just not an ideal solution, but no one else seems to have come up
with anything better yet.  This may be the first module to provide the ability
to migrate data from a dynamic source without requiring a developer to write
configuration.
