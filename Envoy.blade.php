@servers(['web' => $server])

@setup
$repository = 'git@github.com:infsolution/pix-api.git';
echo "Set up repository {{ $repository }}";
$base_dir = '/var/www/';
$releases_dir = $base_dir . $path . '/releases';
$app_dir = $base_dir . $path;
$release = date('YmdHis');
$new_release_dir = $releases_dir .'/'. $release;

function logMessage($message) {
return "echo '\033[32m" .$message. "\033[0m';\n";
}
@endsetup

@story('deploy')
clone_repository
run_composer
update_symlinks
wipe
optimize
clean_old_releases
@endstory

@task('clone_repository')
{{ logMessage('Cloning repository') }}
[ -d {{ $releases_dir }} ] || mkdir -p {{ $releases_dir }}
git clone --depth 1 --branch {{ $branch }} {{ $repository }} {{ $new_release_dir }}
cd {{ $new_release_dir }}
git reset --hard {{ $commit }}
@endtask

@task('run_composer')

{{ logMessage('runing composer') }}
cd {{ $new_release_dir }}
composer install --prefer-dist --no-scripts -o

@endtask

@task('wipe')
if [ {{ $wipe }} ]; then
php {{ $app_dir }}/current/artisan db:wipe
fi
@endtask

@task('migrate')
{{ logMessage('Starting migrate') }}
rm -rf {{ $new_release_dir }}/.env.testing
php {{ $app_dir }}/current/artisan migrate --force
php {{$app_dir}}/current/artisan db:seed --class=SystemConfigurationSeeder --force
php {{$app_dir}}/current/artisan db:seed --class=BasePriceSeeder --force
@endtask

@task('optimize')
{{ logMessage('Starting cache') }}

{{ logMessage('Config cache') }}
php {{ $app_dir }}/current/artisan optimize


{{ logMessage('Storage link cache') }}
sudo php {{ $app_dir }}/current/artisan storage:link
sudo chown -R $USER:www-data {{ $app_dir }}/current/bootstrap/cache
sudo find {{ $app_dir }}/current/bootstrap/cache -type f -exec chmod 664 {} + ;
sudo find {{ $app_dir }}/current/bootstrap/cache -type d -exec chmod 775 {} + ;
@endtask

@task('set_permissions')
# Set dir permissions
{{ logMessage('Set permissions') }}

chmod -R ug+rwx {{ $app_dir }}/storage
cd {{ $app_dir }}
chmod -R ug+rwx current/storage
chmod -R ug+rwx current/bootstrap/cache
@endtask


@task('update_symlinks')
{{ logMessage($new_release_dir) }}
{{ logMessage($app_dir) }}
{{ logMessage($releases_dir) }}

{{ logMessage('Linking storage directory') }}

rm -rf {{ $new_release_dir }}/storage

ln -nfs {{ $app_dir }}/storage {{ $new_release_dir }}/storage

ln -nfs {{ $base_dir }}/html/storage {{ $app_dir }}/storage


{{ logMessage('Linking .env file') }}
ln -nfs {{ $app_dir }}/.env {{ $new_release_dir }}/.env

{{ logMessage('Linking current release') }}
ln -nfs {{ $new_release_dir }} {{ $app_dir }}/current
sudo chown -R deployer:www-data {{ $app_dir }}/current
@endtask

@task('clean_old_releases')
# Delete all but the 5 most recent releases
{{ logMessage('Cleaning old releases') }}
cd {{ $releases_dir }}
sudo ls -dt {{ $releases_dir }}/* | tail -n +2 | xargs -d "\n" rm -rf;
@endtask