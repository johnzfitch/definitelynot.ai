# Compute and Hosting Resource Plan

**Document Version:** 1.0
**Date:** 2025-11-16
**Target Audience:** DevOps, Infrastructure Teams, Hosting Providers

---

## Overview

This document outlines compute, memory, storage, and network resource requirements for deploying the Cosmic Text Linter at various scales, from personal projects on shared hosting to high-traffic production SaaS deployments.

---

## Deployment Tiers

### Tier 1: Shared Hosting (Personal / Low Traffic)

**Use Case:** Personal website, blog, low-traffic application (<100 req/hour)

#### Requirements
| Resource | Minimum | Recommended | Notes |
|----------|---------|-------------|-------|
| **PHP Version** | 7.4 | 8.1+ | Better performance on 8.x |
| **Memory (per request)** | 32 MB | 64 MB | Typical request uses 10-30 MB |
| **PHP Extensions** | mbstring, intl | mbstring, intl, opcache | OPcache reduces CPU by ~30% |
| **CPU** | Shared | Shared | <1% CPU per request on small inputs |
| **Disk Space** | 5 MB | 10 MB | Code + logs |
| **Bandwidth** | 1 GB/month | 5 GB/month | Depends on traffic |

#### Configuration

**php.ini settings (cPanel / shared hosting):**
```ini
memory_limit = 128M
max_execution_time = 30
post_max_size = 2M
upload_max_filesize = 2M
```

**.htaccess overrides (if allowed):**
```apache
php_value memory_limit 128M
php_value max_execution_time 30
php_value post_max_size 2M
```

#### Caveats
- **No process isolation:** Shared hosting environments may impose strict resource limits
- **Limited logging:** May not have access to custom log files
- **No caching layers:** Redis/Memcached typically not available
- **CORS restrictions:** May need to adjust CORS headers for production domain

#### Cost Estimate
- **Shared hosting:** $5-15/month (e.g., Bluehost, HostGator, SiteGround)
- **Includes:** PHP, MySQL (if needed), SSL, domain

#### Performance Expectations (Post-Phase 1 Optimization)
| Input Size | Expected Latency (P95) | Throughput |
|------------|------------------------|------------|
| 100 bytes  | 0.5 ms | ~2 MB/s |
| 1 KB       | 2 ms | ~0.5 MB/s |
| 10 KB      | 10 ms | ~1 MB/s |
| 100 KB     | 100 ms | ~1 MB/s |

---

### Tier 2: VPS / Cloud Instance (Small to Medium Traffic)

**Use Case:** Small business, API service, moderate traffic (100-1000 req/hour)

#### Requirements
| Resource | Minimum | Recommended | Notes |
|----------|---------|-------------|-------|
| **vCPUs** | 1 | 2 | 2 vCPUs handle ~500 req/min |
| **RAM** | 1 GB | 2 GB | PHP-FPM pool + caching |
| **Storage** | 20 GB SSD | 50 GB SSD | Logs, backups |
| **Bandwidth** | 1 TB/month | 2 TB/month | ~100 KB avg response size |
| **OS** | Ubuntu 20.04+ | Ubuntu 22.04 LTS | Or Debian 11+ |
| **Web Server** | Nginx or Apache | Nginx | Nginx more efficient |
| **PHP-FPM** | Workers: 5 | Workers: 10-20 | Based on load |

#### Architecture

```
[Internet]
    |
[Nginx] (reverse proxy, SSL termination)
    |
[PHP-FPM] (10 workers, 128 MB each)
    |
[Cosmic Text Linter API]
    |
[Optional: Redis] (result caching)
```

#### Configuration

**Nginx (`/etc/nginx/sites-available/linter`):**
```nginx
server {
    listen 443 ssl http2;
    server_name api.example.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    root /var/www/linter;
    index index.html;

    location /api/ {
        try_files $uri $uri/ /api/clean.php?$args;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_read_timeout 30s;
    }

    location / {
        try_files $uri $uri/ =404;
    }
}
```

**PHP-FPM (`/etc/php/8.1/fpm/pool.d/www.conf`):**
```ini
pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 10
pm.max_requests = 500

php_admin_value[memory_limit] = 128M
php_admin_value[max_execution_time] = 30
```

#### Caching Strategy

**Redis for result caching (optional, Phase 4):**
- Cache clean text results by hash of (input, mode)
- TTL: 1 hour
- Memory: 256 MB Redis instance
- Hit rate: 20-40% (depends on input diversity)

#### Cost Estimate
- **VPS (2 vCPU, 2 GB RAM):** $10-20/month (DigitalOcean, Linode, Vultr)
- **Optional Redis:** $5-10/month or included in VPS
- **Managed hosting (Platform.sh, Heroku):** $25-50/month

#### Performance Expectations (Post-Phase 1)
| Metric | Value |
|--------|-------|
| **Sustained throughput** | 500-1000 req/min |
| **Burst throughput** | 2000 req/min (30s burst) |
| **Median latency** | 2-5 ms (1KB input) |
| **P95 latency** | 10 ms |
| **P99 latency** | 20 ms |

---

### Tier 3: Dedicated Server (High Traffic / Production)

**Use Case:** High-traffic SaaS, enterprise API, critical infrastructure (1000+ req/min)

#### Requirements
| Resource | Minimum | Recommended | Notes |
|----------|---------|-------------|-------|
| **CPUs** | 4 cores | 8+ cores | 16 cores for >5K req/min |
| **RAM** | 8 GB | 16 GB | Accommodate PHP-FPM pool |
| **Storage** | 100 GB SSD | 500 GB NVMe SSD | Fast disk for logs |
| **Bandwidth** | 10 TB/month | Unmetered | High volume |
| **Load Balancer** | Optional | Recommended | HA setup |
| **Caching** | Redis | Redis Cluster | Multi-GB cache |

#### Architecture

```
[Internet]
    |
[Load Balancer] (HAProxy, AWS ALB, Cloudflare)
    |
    +---[Nginx + PHP-FPM] (App Server 1)
    |       |
    |   [Cosmic Text Linter]
    |
    +---[Nginx + PHP-FPM] (App Server 2)
    |       |
    |   [Cosmic Text Linter]
    |
    +---[Nginx + PHP-FPM] (App Server N)
            |
        [Redis Cluster] (caching layer)
            |
        [Prometheus + Grafana] (monitoring)
```

#### Configuration

**PHP-FPM per app server:**
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 10
pm.max_spare_servers = 20
pm.max_requests = 1000

php_admin_value[memory_limit] = 256M
```

**Redis Cluster:**
- 3-node cluster (high availability)
- 4 GB RAM per node
- Persistent storage for cache warmup

**Monitoring:**
- Prometheus scrapes `/metrics` endpoint (Phase 4)
- Grafana dashboards: request rate, latency, error rate, cache hit ratio
- Alerting: PagerDuty / Slack integration

#### Cost Estimate
- **Dedicated server (8 cores, 16 GB RAM):** $50-150/month (OVH, Hetzner, AWS EC2)
- **Load balancer:** $20-50/month (managed) or free (HAProxy)
- **Redis Cluster (3 nodes):** $30-60/month
- **Monitoring (Grafana Cloud):** $0-50/month (depending on usage)
- **CDN (Cloudflare):** $0-20/month
- **Total:** $100-330/month

#### Performance Expectations (Post-Phase 2)
| Metric | Value |
|--------|-------|
| **Sustained throughput** | 5,000-10,000 req/min |
| **Burst throughput** | 20,000 req/min |
| **Median latency** | 1-3 ms (1KB input) |
| **P95 latency** | 5 ms |
| **P99 latency** | 10 ms |
| **Availability** | 99.9% (HA setup) |

---

### Tier 4: Cloud-Native / Containerized (Massive Scale)

**Use Case:** Global SaaS, millions of requests/day, multi-region deployment

#### Requirements
- **Platform:** Kubernetes (GKE, EKS, AKS) or serverless (AWS Lambda, Google Cloud Run)
- **Auto-scaling:** Horizontal pod autoscaler (HPA) based on CPU/request rate
- **Global distribution:** Multi-region deployment with geo-routing
- **Database:** None required (stateless API)
- **Caching:** Redis/Memcached cluster or managed cache (AWS ElastiCache)

#### Architecture

```
[Global Load Balancer] (Cloudflare, AWS Global Accelerator)
    |
    +---[Region US-East]
    |     |
    |     +---[K8s Cluster]
    |           |
    |           +---[Pod 1: Nginx + PHP-FPM]
    |           +---[Pod 2: Nginx + PHP-FPM]
    |           +---[Pod N: Nginx + PHP-FPM]
    |           |
    |           +---[Redis Cluster]
    |
    +---[Region EU-West]
          |
          +---[K8s Cluster] (same as above)
```

#### Kubernetes Deployment

**`deployment.yaml`:**
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: cosmic-linter
spec:
  replicas: 10
  selector:
    matchLabels:
      app: cosmic-linter
  template:
    metadata:
      labels:
        app: cosmic-linter
    spec:
      containers:
      - name: linter
        image: your-registry/cosmic-linter:latest
        ports:
        - containerPort: 80
        resources:
          requests:
            cpu: 200m
            memory: 256Mi
          limits:
            cpu: 500m
            memory: 512Mi
        env:
        - name: LINTER_LOG_LEVEL
          value: "info"
        - name: REDIS_HOST
          value: "redis-cluster"
---
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: cosmic-linter-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: cosmic-linter
  minReplicas: 5
  maxReplicas: 100
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
```

#### Cost Estimate
- **GKE/EKS cluster (small):** $150-300/month (control plane + 5 nodes)
- **Nodes (auto-scaling pool):** $0.05-0.15 per hour per pod (spot instances)
- **Redis Cluster (managed):** $100-300/month
- **Load balancer:** $20-50/month
- **Monitoring (Datadog, New Relic):** $100-500/month
- **Total (baseline):** $400-1500/month
- **At scale (100 pods):** $2000-5000/month

#### Performance Expectations
| Metric | Value |
|--------|-------|
| **Sustained throughput** | 100,000+ req/min |
| **Burst throughput** | 500,000+ req/min (auto-scaling) |
| **Median latency** | <2 ms (global CDN) |
| **P99 latency** | <10 ms |
| **Availability** | 99.99% (multi-region HA) |

---

## Resource Consumption Breakdown

### Per-Request Resource Usage (Current Baseline)

| Input Size | Memory Peak | CPU Time | Disk I/O |
|------------|-------------|----------|----------|
| 100 bytes  | 8 MB        | 0.1 ms   | None     |
| 1 KB       | 12 MB       | 1.2 ms   | None     |
| 10 KB      | 25 MB       | 75 ms    | None     |
| 100 KB     | 120 MB      | 750 ms   | None     |

### After Phase 1 Optimization (Projected)

| Input Size | Memory Peak | CPU Time | Disk I/O |
|------------|-------------|----------|----------|
| 100 bytes  | 8 MB        | 0.1 ms   | None     |
| 1 KB       | 12 MB       | 1.0 ms   | None     |
| 10 KB      | 25 MB       | 7.5 ms   | None     |
| 100 KB     | 120 MB      | 75 ms    | None     |

---

## Caching Strategy and Storage Needs

### Result Caching (Optional, Phase 4)

**Approach:**
- Cache sanitized text by hash of `(input, mode)`
- Use Redis with LRU eviction
- TTL: 1 hour (adjustable)

**Cache Size Estimation:**

| Daily Requests | Unique Inputs (est. 30%) | Avg Input Size | Cache Size |
|----------------|--------------------------|----------------|------------|
| 10,000         | 3,000                    | 1 KB           | 3 MB       |
| 100,000        | 30,000                   | 1 KB           | 30 MB      |
| 1,000,000      | 300,000                  | 1 KB           | 300 MB     |
| 10,000,000     | 3,000,000                | 1 KB           | 3 GB       |

**Recommendation:**
- **<100K req/day:** 128 MB Redis instance
- **100K-1M req/day:** 512 MB - 1 GB Redis
- **>1M req/day:** 2-4 GB Redis cluster

---

## Log Storage Requirements

### Log Volume Estimation

**Assumptions:**
- Log level: `info` (default for production)
- Avg log entry size: 200 bytes (JSON structured)
- Entries per request: 2 (request start + end with stats)

| Daily Requests | Daily Log Size | 30-Day Retention |
|----------------|----------------|------------------|
| 10,000         | 4 MB           | 120 MB           |
| 100,000        | 40 MB          | 1.2 GB           |
| 1,000,000      | 400 MB         | 12 GB            |
| 10,000,000     | 4 GB           | 120 GB           |

**Recommendation:**
- **Tier 1 (Shared):** Disable logging or use `error` level only
- **Tier 2 (VPS):** 10-50 GB disk for logs
- **Tier 3 (Dedicated):** 100-500 GB, ship logs to external service (Papertrail, Loggly)
- **Tier 4 (Cloud):** Centralized logging (CloudWatch, Stackdriver), unlimited retention

---

## Bandwidth and Data Transfer

### Typical API Response Size

| Input Size | Output Size | Overhead (JSON + stats) | Total Response |
|------------|-------------|-------------------------|----------------|
| 100 bytes  | 100 bytes   | 500 bytes               | 600 bytes      |
| 1 KB       | 1 KB        | 500 bytes               | 1.5 KB         |
| 10 KB      | 10 KB       | 500 bytes               | 10.5 KB        |
| 100 KB     | 100 KB      | 500 bytes               | 100.5 KB       |

**Average response size:** ~5 KB (assuming mix of input sizes)

### Bandwidth Estimation

| Daily Requests | Avg Response | Daily Bandwidth | Monthly Bandwidth |
|----------------|--------------|-----------------|-------------------|
| 10,000         | 5 KB         | 50 MB           | 1.5 GB            |
| 100,000        | 5 KB         | 500 MB          | 15 GB             |
| 1,000,000      | 5 KB         | 5 GB            | 150 GB            |
| 10,000,000     | 5 KB         | 50 GB           | 1.5 TB            |

**Recommendation:**
- **Tier 1:** 5 GB/month plan sufficient
- **Tier 2:** 50-200 GB/month
- **Tier 3:** 1-5 TB/month
- **Tier 4:** Unmetered or CDN (Cloudflare free tier: unlimited)

---

## Scaling Guidelines

### Vertical Scaling (Scale Up)

| Traffic Growth | Action |
|----------------|--------|
| 2x requests    | Double PHP-FPM workers, add 50% RAM |
| 5x requests    | Upgrade to next CPU tier, add caching |
| 10x requests   | Move to dedicated server or multi-server setup |

### Horizontal Scaling (Scale Out)

| Traffic Growth | Action |
|----------------|--------|
| 10x requests   | Add 2nd app server behind load balancer |
| 100x requests  | Deploy Kubernetes cluster with auto-scaling |
| 1000x requests | Multi-region deployment, global CDN |

---

## Cost Summary

| Tier | Traffic (req/min) | Monthly Cost | Notes |
|------|-------------------|--------------|-------|
| **Tier 1: Shared** | <10 | $5-15 | Personal projects |
| **Tier 2: VPS** | 10-500 | $10-50 | Small business, API |
| **Tier 3: Dedicated** | 500-5000 | $100-300 | Production, SaaS |
| **Tier 4: Cloud** | 5000+ | $400-5000+ | Enterprise, global scale |

---

## Recommendations

### For Most Users (Tier 1-2)
- Start with shared hosting or small VPS
- Enable OPcache for 30% performance boost
- Monitor with simple access logs
- Upgrade when sustained traffic >100 req/hour

### For Production (Tier 3)
- Use dedicated server or managed PaaS
- Implement Redis caching (20-40% cache hit rate)
- Set up monitoring (Prometheus + Grafana)
- Plan for 2x headroom to handle traffic spikes

### For Enterprise (Tier 4)
- Deploy on Kubernetes for auto-scaling
- Multi-region for global latency <50ms
- Implement rate limiting and API keys
- Centralized logging and alerting

---

## Appendix: Hosting Provider Comparisons

| Provider | Tier | vCPU | RAM | Storage | Bandwidth | Cost/Month |
|----------|------|------|-----|---------|-----------|------------|
| **DigitalOcean** | Droplet | 2 | 2 GB | 50 GB SSD | 2 TB | $18 |
| **Linode** | Shared | 2 | 4 GB | 80 GB SSD | 4 TB | $24 |
| **Vultr** | Cloud Compute | 2 | 4 GB | 80 GB SSD | 3 TB | $24 |
| **AWS EC2** | t3.small | 2 | 2 GB | 20 GB EBS | Pay-as-you-go | ~$25 |
| **Hetzner** | CX21 | 2 | 4 GB | 40 GB SSD | 20 TB | €5.83 (~$6) |
| **OVH** | VPS Starter | 1 | 2 GB | 20 GB SSD | Unlimited | $6 |

**Winner (best value):** Hetzner CX21 for Tier 2, OVH for budget Tier 2

---

**Maintained by:** Infrastructure Team
**Last Updated:** 2025-11-16
**Next Review:** After Phase 1 optimization (update performance metrics)
