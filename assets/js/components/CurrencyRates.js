import React, { useState, useEffect } from 'react';
import axios from 'axios';
import '../../css/currency-exchange.css';

const CurrencyRates = () => {
    const [currentRates, setCurrentRates] = useState({});
    const [historicalRates, setHistoricalRates] = useState({});
    const [selectedDate, setSelectedDate] = useState(new Date());
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const formatDate = (date) => {
        return date.toISOString().split('T')[0];
    };

    const fetchCurrentRates = async () => {
        try {
            const response = await axios.get('/api/rates/current');
            setCurrentRates(response.data);
            setError(null);
        } catch (err) {
            setError('Error fetching current rates');
            console.error(err);
        }
    };

    const fetchHistoricalRates = async (date) => {
        try {
            const response = await axios.get(`/api/rates/historical?date=${formatDate(date)}`);
            setHistoricalRates(response.data);
            setError(null);
        } catch (err) {
            setError('Error fetching historical rates');
            console.error(err);
        }
    };

    useEffect(() => {
        const loadData = async () => {
            setLoading(true);
            await Promise.all([
                fetchCurrentRates(),
                fetchHistoricalRates(selectedDate)
            ]);
            setLoading(false);
        };

        loadData();
    }, []);

    const handleDateChange = (date) => {
        setSelectedDate(date);
        fetchHistoricalRates(date);
    };

    if (loading) {
        return (
            <div className="currency-exchange">
                <div className="header">
                    <div className="container">
                        <h1 className="currency-header text-center">Currency Exchange Office</h1>
                    </div>
                </div>
                <div className="container">
                    <div className="loading-spinner">
                        <div className="spinner"></div>
                    </div>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="currency-exchange">
                <div className="header">
                    <div className="container">
                        <h1 className="currency-header text-center">Currency Exchange Office</h1>
                    </div>
                </div>
                <div className="container mt-4">
                    <div className="currency-card">
                        <div className="card-body">
                            <div className="alert alert-danger mb-0">
                                <h4 className="alert-heading">Error</h4>
                                <p className="mb-0">{error}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="currency-exchange">
            <div className="header">
                <div className="container">
                    <h1 className="currency-header text-center">Currency Exchange Office</h1>
                    <p className="text-center text-light mb-0">Real-time exchange rates for major currencies</p>
                </div>
            </div>

            <div className="container">
                <div className="currency-card">
                    <div className="card-header d-flex justify-content-between align-items-center">
                        <h2 className="h4 mb-0">Current Exchange Rates</h2>
                        <small className="text-muted">Updated every hour</small>
                    </div>
                    <div className="card-body">
                        <div className="table-responsive">
                            <table className="table currency-table">
                                <thead>
                                    <tr>
                                        <th>Currency</th>
                                        <th>Average Rate</th>
                                        <th>Buying Rate</th>
                                        <th>Selling Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {Object.values(currentRates).map((rate) => (
                                        <tr key={rate.code}>
                                            <td>
                                                <div className="currency-code">{rate.code}</div>
                                                <div className="currency-name">{rate.currency}</div>
                                            </td>
                                            <td className="rate-value">{rate.averageRate.toFixed(4)} PLN</td>
                                            <td className="rate-value">
                                                {rate.buyingRate 
                                                    ? <span className="buying-rate">{rate.buyingRate.toFixed(4)} PLN</span>
                                                    : <span className="na-rate">Not buying</span>
                                                }
                                            </td>
                                            <td className="rate-value">
                                                <span className="selling-rate">{rate.sellingRate.toFixed(4)} PLN</span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div className="currency-card">
                    <div className="card-header">
                        <div className="d-flex justify-content-between align-items-center">
                            <h2 className="h4 mb-0">Historical Rates</h2>
                            <div className="date-picker-container">
                                <input
                                    type="date"
                                    className="form-control"
                                    value={selectedDate.toISOString().split('T')[0]}
                                    onChange={(e) => handleDateChange(new Date(e.target.value))}
                                    max={new Date().toISOString().split('T')[0]}
                                />
                            </div>
                        </div>
                    </div>
                    <div className="card-body">
                        {Object.entries(historicalRates).map(([currency, rates]) => (
                            <div key={currency} className="historical-rates-section mb-4">
                                <h3 className="currency-section-title">
                                    <span className="currency-code">{currency}</span>
                                    <small className="text-muted ml-2">Last 14 days</small>
                                </h3>
                                <div className="table-responsive">
                                    <table className="table currency-table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Average Rate</th>
                                                <th>Buying Rate</th>
                                                <th>Selling Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rates.map((rate) => (
                                                <tr key={rate.date}>
                                                    <td>{rate.date}</td>
                                                    <td className="rate-value">{rate.averageRate.toFixed(4)} PLN</td>
                                                    <td className="rate-value">
                                                        {rate.buyingRate 
                                                            ? <span className="buying-rate">{rate.buyingRate.toFixed(4)} PLN</span>
                                                            : <span className="na-rate">Not buying</span>
                                                        }
                                                    </td>
                                                    <td className="rate-value">
                                                        <span className="selling-rate">{rate.sellingRate.toFixed(4)} PLN</span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default CurrencyRates;
