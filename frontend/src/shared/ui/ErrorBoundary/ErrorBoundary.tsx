import { Button, Result } from 'antd';
import { Component, type ErrorInfo, type ReactNode } from 'react';

interface Props {
  children: ReactNode;
}

interface State {
  error: Error | null;
}

export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // eslint-disable-next-line no-console
    console.error('Необработанная ошибка приложения:', error, info.componentStack);
  }

  handleReload = () => {
    window.location.reload();
  };

  render() {
    if (this.state.error) {
      return (
        <Result
          status="error"
          title="Что-то пошло не так"
          subTitle="Страница столкнулась с непредвиденной ошибкой. Попробуйте перезагрузить её."
          extra={
            <Button type="primary" onClick={this.handleReload}>
              Перезагрузить страницу
            </Button>
          }
          style={{ paddingTop: '15vh' }}
        />
      );
    }

    return this.props.children;
  }
}
