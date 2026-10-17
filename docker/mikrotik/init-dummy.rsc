# ------------------------------------------------------------------------------
# TikPulse Dummy MikroTik RouterOS Initial Configuration Script (.rsc)
# Target: RouterOS v7.x (Cloud Hosted Router)
# ------------------------------------------------------------------------------

# 1. System Identity
/system identity set name="TikPulse-Lab-CHR"

# 2. Enable Services
/ip service enable api
/ip service enable api-ssl
/ip service enable www
/ip service enable winbox

# 3. Security (Set admin password)
/user set admin password="admin123"

# 4. Hotspot Bandwidth Profiles
/ip hotspot user profile
add name="1Hour-Regular" rate-limit="2M/5M" shared-users=1 mac-cookie-timeout=1h
add name="1Day-Unlimited" rate-limit="5M/10M" shared-users=1 mac-cookie-timeout=1d
add name="1Month-VIP" rate-limit="10M/20M" shared-users=2 mac-cookie-timeout=30d

# 5. Sample Hotspot Test Users
/ip hotspot user
add name="test01" password="test01" profile="1Hour-Regular" comment="TikPulse Demo Voucher"
add name="vip01" password="vip01" profile="1Month-VIP" comment="TikPulse Demo VIP Voucher"

# 6. PPP Profiles (Dedicated Users / PPPoE)
/ppp profile
add name="10Mbps-Dedicated" rate-limit="10M/10M" only-one=yes
add name="20Mbps-Dedicated" rate-limit="20M/20M" only-one=yes

# 7. Sample PPP Secrets
/ppp secret
add name="pppoe-demo" password="demopass123" service=pppoe profile="10Mbps-Dedicated" comment="TikPulse Demo PPP Subscriber"

:log info "TikPulse Dummy MikroTik configuration initialized successfully!"
