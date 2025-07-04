import http from 'k6/http';
import { sleep } from 'k6';

export const options = {
    vus: 100, 
    duration: '30s',
    thresholds: {
        http_req_duration: ['p(95)<500'], 
    },
};

const BASE_URL = 'http://127.0.0.1:8000';

export default function () {
    const loginPayload = JSON.stringify({
            login: "topengkayu07@gmail.com",
            password: "SitiR456@"
    });

    const params = {
        headers: {
            'Content-Type': 'application/json',
        },
    };

    http.post(`${BASE_URL}/api/auth/login`, loginPayload, params);
    sleep(1);

    http.get(`${BASE_URL}/`);
    sleep(1);

    http.get(`${BASE_URL}/api/waste-categories`);
    sleep(1);
}