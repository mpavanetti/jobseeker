#!/bin/sh
# Runs a service as the uid:gid that owns the JobSeeker checkout, so what it
# writes to bind mounts belongs to the host user on any machine. Only acts when
# started as root (Compose); Kubernetes and plain `docker run` pass through.
#
#   JOBSEEKER_RUNTIME_USER     account to realign and run as
#   JOBSEEKER_OWNER_REFERENCE  mounted path owned by the checkout owner
#   JOBSEEKER_OWNED_PATHS      paths outside the checkout to re-own on mismatch
set -eu

if [ "$(id -u)" != 0 ]; then
	exec "$@"
fi

user="$JOBSEEKER_RUNTIME_USER"
uid="$(stat -c %u "$JOBSEEKER_OWNER_REFERENCE")"
gid="$(stat -c %g "$JOBSEEKER_OWNER_REFERENCE")"
image_gid="$(id -g "$user")"

# A root-owned checkout (rootless Docker, where root is the host user, or a
# clone made as root) has nobody to switch to.
if [ "$uid" = 0 ]; then
	exec "$@"
fi

if [ "$(id -u "$user")" != "$uid" ] || [ "$image_gid" != "$gid" ]; then
	sed -i "s/^\($(id -gn "$user"):[^:]*\):[0-9]*:/\1:$gid:/" /etc/group
	sed -i "s/^\($user:[^:]*\):[0-9]*:[0-9]*:/\1:$uid:$gid:/" /etc/passwd
	# Keep the image's group too, so its group-writable paths stay writable.
	[ "$image_gid" = "$gid" ] || echo "jobseeker-image:x:$image_gid:$user" >> /etc/group
	echo "[JobSeeker] Running as $user ($uid:$gid), the owner of the checkout."
fi

for path in ${JOBSEEKER_OWNED_PATHS:-}; do
	if [ -e "$path" ]; then
		chown -Rh "$uid:$gid" "$path"
	fi
done

# Docker opens stdout/stderr as root and php-fpm reopens them by path. No
# 2>/dev/null: BusyBox runs chown in-process, so it would redirect fd 2 itself.
chown "$uid:$gid" "/proc/$$/fd/1" "/proc/$$/fd/2" || true

if [ "${HOME:-/root}" = /root ]; then
	HOME="$(awk -F: -v u="$user" '$1 == u { print $6 }' /etc/passwd)"
	export HOME
fi

# BusyBox's setpriv cannot switch users, so Alpine images use su; "--" keeps
# su from parsing the command's own options.
if setpriv --help 2>&1 | grep -q -- --reuid; then
	exec setpriv --reuid="$uid" --regid="$gid" --init-groups "$@"
fi
exec su -m -s /bin/sh -c 'exec "$@"' -- "$user" sh "$@"
