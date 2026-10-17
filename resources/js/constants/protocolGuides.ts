import type { RouterProtocol } from '@/types/router';

export interface ProtocolGuide {
    id: RouterProtocol;
    name: string;
    port: number;
    badge: string;
    recommendationSummary: string;
    isPrimaryRecommended: boolean;
    description: string;
    bestFor: string;
    pros: string[];
    cons: string[];
    mikrotikCommands: string;
    setupNote: string;
}

export const PROTOCOL_GUIDES: Record<RouterProtocol, ProtocolGuide> = {
    socket: {
        id: 'socket',
        name: 'Socket API (Binary Protocol)',
        port: 8728,
        badge: 'Recommended for Local / LAN',
        recommendationSummary: 'Primary recommendation for local networks & high-speed voucher operations',
        isPrimaryRecommended: true,
        description:
            'Native high-speed binary socket driver. Delivers sub-millisecond response times, the lowest CPU usage, and 100% compatibility across both RouterOS v6.x and v7.x.',
        bestFor: 'Hotspot cafés, RT/RW Net, office LANs, and high-frequency live traffic monitoring.',
        pros: [
            'Lowest latency & minimal CPU overhead',
            'Full support on all RouterOS v6.x & v7.x versions',
            'No SSL certificates or domain setup required',
            'Proven high reliability for bulk voucher operations',
        ],
        cons: [
            'Transfers unencrypted on port 8728 (recommended within trusted LAN or VPN only)',
        ],
        mikrotikCommands: `/ip service set api disabled=no port=8728
/user group add name=tikpulse policy=api,read,write,test,password comment="TikPulse Management Group"
/user add name=tikpulse group=tikpulse password="YOUR_STRONG_PASSWORD" comment="TikPulse Operator"`,
        setupNote: 'Open Winbox > Terminal or SSH into your MikroTik, then run the commands above. Replace YOUR_STRONG_PASSWORD with your desired password.',
    },
    socket_ssl: {
        id: 'socket_ssl',
        name: 'Socket SSL (Encrypted Binary)',
        port: 8729,
        badge: 'Recommended for Secure Remote / WAN',
        recommendationSummary: 'Best for remote routers connected over Public IP / WAN with TLS encryption',
        isPrimaryRecommended: false,
        description:
            'TLS-encrypted binary socket protocol. Provides full cryptographic protection for management credentials and voucher operations across untrusted public networks or branch offices.',
        bestFor: 'Branch office routers managed over the public internet, remote towers, and compliance-sensitive networks.',
        pros: [
            'End-to-end TLS encryption across public networks',
            'Same fast binary protocol efficiency as socket',
            'Compatible with both RouterOS v6.x and v7.x',
        ],
        cons: [
            'Requires an active SSL certificate imported or generated in MikroTik (/certificate)',
        ],
        mikrotikCommands: `/ip service set api-ssl disabled=no port=8729 certificate=your_ssl_cert
/user group add name=tikpulse policy=api,read,write,test,password comment="TikPulse Management Group"
/user add name=tikpulse group=tikpulse password="YOUR_STRONG_PASSWORD" comment="TikPulse Operator"`,
        setupNote: 'Ensure a valid certificate exists in System > Certificates. Replace "your_ssl_cert" with your certificate name.',
    },
    rest_https: {
        id: 'rest_https',
        name: 'RouterOS v7 REST API (HTTPS)',
        port: 443,
        badge: 'Recommended for ROS v7+ Cloud & Proxies',
        recommendationSummary: 'Best for modern RouterOS v7.1+ CHR / Cloud routers behind standard HTTPS proxies',
        isPrimaryRecommended: false,
        description:
            'Standard HTTPS REST API introduced in RouterOS v7.1+. Communicates via JSON payloads over standard web port 443, making it ideal for traversal through corporate firewalls, Cloudflare tunnels, and Nginx reverse proxies.',
        bestFor: 'Cloud Hosted Routers (CHR), VPS instances, and networks where custom ports (8728/8729) are blocked by upstream ISPs.',
        pros: [
            'Operates on standard HTTPS port 443 (firewall-friendly)',
            'Native JSON payloads on RouterOS v7.1+',
            'Easily routes through Cloudflare, Nginx, or Traefik reverse proxies',
        ],
        cons: [
            'Only available on RouterOS v7.1 or newer (incompatible with RouterOS v6)',
            'Slightly higher CPU/memory overhead than binary socket',
        ],
        mikrotikCommands: `/ip service set www-ssl disabled=no port=443 certificate=your_ssl_cert
/user group add name=tikpulse policy=read,write,rest-api,test,password comment="TikPulse REST Group"
/user add name=tikpulse group=tikpulse password="YOUR_STRONG_PASSWORD" comment="TikPulse Operator"`,
        setupNote: 'Requires RouterOS v7.1+. Enable www-ssl service with a valid certificate and ensure the user group includes the rest-api policy.',
    },
};
