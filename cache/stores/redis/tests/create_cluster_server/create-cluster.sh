#!/bin/sh
set -o xtrace

REDIS_EXEC=`which redis-server`
TMP_PATH="/tmp/rediscluster"
SCRIPT_FILE=`realpath $0`
SCRIPT_FILE_PATH=`dirname "${SCRIPT_FILE}"`
REPLICAS=""

for PORT in `seq 7000 7005`;
do
    DIR="${TMP_PATH}/${PORT}"
    CFG="${DIR}/redis.conf"
    REPLICAS="${REPLICAS} 127.0.0.1:${PORT}"

    mkdir -p "${DIR}"
    cd "${DIR}"

    echo "# Redis configuration for ${PORT}"         > "${CFG}"
    echo "pidfile /var/run/redis/redis-${PORT}.pid" >> "${CFG}"
    echo "port ${PORT}"                             >> "${CFG}"
    echo 'cluster-enabled yes'                      >> "${CFG}"
    echo "cluster-config-file ${DIR}/nodes.conf"    >> "${CFG}"

    cat "${CFG}"
    "${REDIS_EXEC}" "${CFG}" >/dev/null &
done

cd "${TMP_PATH}"
cp "${SCRIPT_FILE_PATH}/redis-trib.rb" .
echo yes | ./redis-trib.rb create --replicas 1 ${REPLICAS}
