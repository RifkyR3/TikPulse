#!/usr/bin/env bash

# Ensure /dev/net/tun exists
if [ ! -c /dev/net/tun ]; then
    mkdir -p /dev/net
    mknod /dev/net/tun c 10 200
    chmod 666 /dev/net/tun
fi

QEMU_BRIDGE_ETH1='qemubr1'
default_dev1='eth0'

DUMMY_DHCPD_IP='10.0.0.1'

QEMU_IFUP='/routeros/qemu-ifup'
QEMU_IFDOWN='/routeros/qemu-ifdown'

DHCPD_CONF_FILE='/routeros/dhcpd.conf'

/routeros/generate-dhcpd-conf.py $QEMU_BRIDGE_ETH1 >$DHCPD_CONF_FILE

function prepare_intf() {
   ip addr flush dev $1
   ip link add $2 type bridge
   ip link set dev $1 master $2
   ip link set dev $1 up
   ip link set dev $2 up
}

prepare_intf $default_dev1 $QEMU_BRIDGE_ETH1
udhcpd -I $DUMMY_DHCPD_IP -f $DHCPD_CONF_FILE &

CPU_FEATURES=""
KVM_OPTS=""
CPU_MODEL="qemu64"

if [ -e /dev/kvm ]; then
   if grep -q -e vmx -e svm /proc/cpuinfo; then
      echo "Enabling KVM hardware acceleration"
      CPU_FEATURES=",kvm=on"
      KVM_OPTS="-machine accel=kvm -enable-kvm"
      CPU_MODEL="host"
   fi
fi

if [ "$CPU_FEATURES" = "" ]; then
   echo "KVM not available, running in software emulation mode with CPU model: $CPU_MODEL"
fi

exec qemu-system-x86_64 \
   -serial mon:stdio \
   -nographic \
   -m 512 \
   -smp 2,sockets=1,cores=2,threads=1 \
   -cpu $CPU_MODEL$CPU_FEATURES \
   $KVM_OPTS \
   -nic tap,id=qemu1,mac=54:05:AB:CD:12:31,script=$QEMU_IFUP,downscript=$QEMU_IFDOWN \
   "$@" \
   -hda $ROUTEROS_IMAGE
